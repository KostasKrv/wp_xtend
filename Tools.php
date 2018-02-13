<?php 
class wp_xtend {
	const _PAGE1 = 'my_custom_page1';
	const _PAGE2 = 'my_custom_page2';
	const _ENCODING_BASE = '123456789abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ';

	/**
	*
	*	Convert a number to a given base
	*
	*/
	public static function toBase($num) 
	{
	  	$base 	= self::_ENCODING_BASE;
	  	$b 		= strlen($base);
	  	$r 		= $num  % $b ;
	  	$res 	= $base[$r];
	  	$q 		= floor($num/$b);

	  	while ($q) {
	    	$r 	= $q % $b;
	    	$q 	= floor($q/$b);
	    	$res = $base[$r].$res;
	  	}

	  	return $res;
	}

	/**
	*
	*	Convert a number to base 10
	*
	*/
	public static function to10( $num) 
	{
		$base 	= self::_ENCODING_BASE;
		$b 		= strlen($base);
		$limit 	= strlen($num);
		$res 	= strpos($base,$num[0]);
		for($i=1;$i<$limit;$i++) {
		    $res = $b * $res + strpos($base,$num[$i]);
		}

		return $res;
	}	

	/**
	*
	*	Get a parameter out of domain url
	*
	*/
	public static function getUrlParam($index)
	{
		$url = explode('/', 'http://'. $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
		return !empty($url[$index])?$url[$index]:null;
	}
	
	/**
	*
	*	Get page slug (when the page slug is the second element)
	*
	*/
	public static function isPage($page)
	{
		$slug = explode('?', self::getUrlParam(3))[0];
		return $slug === $page;
	}

	/**
	*
	*	Redirect non www to www, etc...
	*
	*/
	public static function normalizeDomain()
	{
		$home = get_home_url();

		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on") ? "https://" : "http://";
		$referUrl = $protocol .  $_SERVER['HTTP_HOST'] . $_SERVER["REQUEST_URI"];

		if (substr($referUrl, 0, (strlen($home)) ) !== $home) {
		    header('Location: ' . $home . '/' . $_SERVER['REQUEST_URI']);
		    exit;
		}
	}

	/**
	*
	* 	Require a simple login for a page based on cookie
	*
	*/
	public static function requireLogin()
	{
		$fieldName 	= 'logged';
		$correct = md5('MY_SUPER_SERCET_PASSWORD');

		self::normalizeDomain();

		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on") ? "https://" : "http://";
		$referUrl = $protocol .  $_SERVER['HTTP_HOST'] . $_SERVER["REQUEST_URI"];

		if (!empty($_COOKIE[$fieldName]) && $_COOKIE[$fieldName] == $correct){
			setcookie($fieldName, $correct, time() + 60 * 60 * 8, '/');
			return; //access granted			
		} else if (!empty($_POST[$fieldName]) && md5($_POST[$fieldName]) == $correct) {
			setcookie($fieldName, $correct, time() + 60 * 60 * 8, '/');
			header('Location: ' . $referUrl);
			return;	//access granted		
		}

		include(dirname(__FILE__) . '/tpl/header.php');

		if (!empty($_POST[$fieldName])){			
			echo '<p class="alert alert-danger">Wrong password</p>';
		}

		include(dirname(__FILE__) . '/tpl/access.php');
		include(dirname(__FILE__) . '/tpl/footer.php');
		exit;			
	}

	/**	
	*
	*	Enqueue scripts on a list of pages
	*
	*/
	public static function filteredEnqueue()
	{

		$white = array(self::_PAGE1, self::_PAGE2);

		$mark = false;
		foreach ($white as $page) {
			if (self::isPage($page)){
				$mark = true;
				break;
			}
		}

		if (!$mark){
			return;
		}

		$jsFile = get_template_directory_uri() . '-child/wp_xtend/assets/js/wp_xtend.js';		
		wp_enqueue_script( 'wp_xtend', $jsFile, array ( 'jquery' ), 'version', true);

		$cssFile = get_template_directory_uri() . '-child/wp_xtend/assets/css/wp_xtend.css';		
		wp_enqueue_style( 'style-name', $cssFile );
	}

	/**
	*
	*	Example function to init an action on a page in order
	*	to overwrite default behaviour
	*
	*	Note: You also have to init the function with wordpress' hook
	*
	*/
	public static function filterPage1Action()
	{
		if (!self::isPage(self::_PAGE1)) {
			return;
		}

		self::requireLogin();

		$form = 3;

		NinjaFormsWrap::changeDefaultValues($form_id);

		wp_head();
		echo '<center style="width:500px;margin:0 auto;">';
		echo 'I am custom page 1';
		echo do_shortcode('[ninja_form id=3]');
		echo '</center>';

		wp_footer();

		exit;
	}

	/**
	*
	* 	Get post ids with plain sql
	*
	*/
	public static function getPostsWithSql()
	{
		global $wpdb;

		$prefix = $wpdb->prefix;

		$fromTag = !empty($_GET['fromTagLike'])?$_GET['fromTagLike']:null;
		$fromCategoryId = !empty($_GET['fromCategoryId'])?$_GET['fromCategoryId']:null;

		if (empty($fromTag) || empty($fromCategoryId) || empty($setCategoryTo)) {
			exit('Give all get parameters: ?fromTagLike=@&fromCategoryId=@');
		}

		$querystr = "
			SELECT
				A.ID
			FROM
				{$prefix}posts A
			RIGHT JOIN (
				SELECT
					object_id as ID
				FROM
					{$prefix}term_relationships
				WHERE
					term_taxonomy_id IN ({$fromCategoryId})
			) B ON A.ID = B.ID
			WHERE
				A.ID IN (
					SELECT
						object_id
					FROM
						{$prefix}term_relationships
					WHERE
						term_taxonomy_id IN (
							SELECT
								term_id
							FROM
								{$prefix}terms
							WHERE
								name LIKE '%{$fromTag}%'
						)
				)
			AND post_status = 'publish'
			AND post_type = 'POST'
		";
		
		$rows = $wpdb->get_results($querystr, OBJECT);
		$total = $wpdb->num_rows;

		$implode = array();
		foreach ($rows as $post) {
			$implode[] = $post->ID;
		}

		return $implode;
	}

	/*
	*
	*	Get custom post types
	*
	*/
	public static function getCustomPostType($count = -1)
	{
	    global $post;

	    $args = array(
	        'posts_per_page' => $count,
	        'post_type' => MY_CUSTOM_POST_TYPE,
	        'post_status' => 'publish',
	    );

	    $the_query = new WP_Query($args);

	    $items = array();
	    while ($the_query->have_posts()) : 
	    	$the_query->the_post();
	        $customs = get_fields(get_the_ID());

	        $items[] = array(
	            'ID' => get_the_ID(),
	            'content' => get_the_content(),
	            'title' => get_the_title(),
	            'date' => get_the_date('U'), //get as timestamp
	            'thumbnail_id' => get_post_thumbnail_id() ? : NULL,
	            'link' => get_permalink(),
	            'custom1' => !empty($customs['custom1']) ? $customs['custom1'] : NULL,	            
	        );
	    endwhile;

	    wp_reset_query();

	    return $items;
	}

}

/**
*
*	Enqueue the scripts
*
*/
function wpdocs_theme_name_scripts() {
	wp_xtend::filteredEnqueue();
}
add_action( 'wp_enqueue_scripts', 'wpdocs_theme_name_scripts' );

/**
*
*	Our code initialization
*
*/
function xtend() {	
	wp_xtend::filterPage1Action();
}
add_action( 'init', 'xtend' );