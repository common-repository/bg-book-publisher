<?php
	/* 
    Plugin Name: Bg Book Publisher 
    Description: The plugin helps you to publish big book with a detailed structure of chapters and sections and forms table of contents of the book.
    Version: 1.25
    Author: VBog (reworked by BZhuk)
    Author URI: https://bogaiskov.ru 
	License:     GPL2
	Text Domain: bg_bpub
	Domain Path: /languages
*/

/*  Copyright 2017-2021  Vadim Bogaiskov  (email: vadim.bogaiskov@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*****************************************************************************************
	Блок загрузки плагина
	
******************************************************************************************/

// Запрет прямого запуска скрипта
if ( !defined('ABSPATH') ) {
	die( 'Sorry, you are not allowed to access this page directly.' ); 
}

define('BG_BPUB_VERSION', '1.25');

// Устанавливаем крючки
if ( defined('ABSPATH') && defined('WPINC') ) {
// Регистрируем крючок для обработки контента при его сохранении
	add_action( 'save_post', 'bg_bpub_save');

// Регистрируем крючок на удаление плагина
	if (function_exists('register_uninstall_hook')) {
		register_uninstall_hook(__FILE__, 'bg_bpub_deinstall');
	}

// Регистрируем крючок для загрузки интернационализации 
	add_action( 'plugins_loaded', 'bg_bpub_load_textdomain' );
	
	if ( is_admin() ) {
	// Регистрируем крючок для добавления JS скрипта в админке 
		add_action( 'admin_enqueue_scripts' , 'bg_bpub_admin_enqueue_scripts' ); 
	} else {
	// Регистрируем крючок для добавления таблицы стилей для плагина
		add_action( 'wp_enqueue_scripts' , 'bg_bpub_frontend_styles' );
	// Регистрируем фильтр для добавления имени автора книги в заголовок записи
		add_filter( 'the_title', 'add_author_to_page_title', 100, 1 );
	// Запрещаем вывод оглавления в отрывке
		remove_filter('get_the_excerpt', 'wp_trim_excerpt');
		add_filter( 'get_the_excerpt', 'delete_toc_in_excerpt');
	}

// Регистрируем шорт-код book_author
	add_shortcode( 'book_author', 'bg_bpub_book_author_shortcode' );
}

// Загрузка интернационализации
function bg_bpub_load_textdomain() {
  load_plugin_textdomain( 'bg_bpub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
}

// JS скрипт 
function bg_bpub_admin_enqueue_scripts () {
	wp_enqueue_script( 'bg_bpub_proc', plugins_url( 'js/bg_bpub_admin.js', __FILE__ ), false, BG_BPUB_VERSION, true );
	wp_localize_script( 'bg_bpub_proc', 'bg_bpub', 
		array( 
			'nonce' => wp_create_nonce('bg-bpub-nonce') 
		) 
	);
}
	 
// Tаблица стилей для плагина
function bg_bpub_frontend_styles () {
	if(get_post_meta(get_the_ID(), 'the_book',true)){
		wp_enqueue_style( "bg_bpub_styles", plugins_url( "/css/style.css", plugin_basename(__FILE__) ), array() , BG_BPUB_VERSION  );
		wp_enqueue_script( 'bg_bpub_js', plugins_url( 'js/bg_bpub.js', __FILE__ ), false, BG_BPUB_VERSION, true );
	}
}

// Добавляем имя автора книги в заголовок записи
function add_author_to_page_title( $title ) {
	global $post, $author_place;
	if ($author_place == 'none' || empty($post)) return $title;
	
	$book_author = bg_bpub_book_author($post->ID);
	
	if (!$book_author) return $title;
	
	// убедимся что мы редактируем заголовок нужного типа поста
	$postTypes = apply_filters('bg_bpub_post_types', ['post', 'page']);
	$single = is_singular( $postTypes );
	$archive = is_post_type_archive($postTypes) && in_the_loop();
	if ( $single || $archive ) {
		$delim = $archive ? ' ' : ' <br>';
		if ($author_place == 'after') $title = $title.$delim.$book_author;
		else if ($author_place == 'before') $title = $book_author.$delim.$title;
		
		$title = apply_filters('bg_bpub_title', $title);
	}
	return $title;
}
// Имя автора книги
function bg_bpub_book_author($post_id) {
	
	$book_author = get_post_meta($post_id, 'book_author',true);
	return ((!$book_author)? "" : '<span class=bg_bpub_book_author>'.$book_author.'</span>');
}

// [book_author]
function bg_bpub_book_author_shortcode ( $atts, $content = null ) {
	$post = get_post();
	 return bg_bpub_book_author($post->ID);
}
// Выполняется при удалении плагина
function bg_bpub_deinstall() {
	// Удаляем опции
	delete_option('bg_bpub_options');
	
	// Удаляем мета-поля в постах
	$args = array(
		'numberposts' => -1,
		'post_type' => apply_filters('bg_bpub_post_types', ['post', 'page']),
		'post_status' => 'any'
	);
	$allposts = get_posts($args);
	foreach( $allposts as $postinfo) {
		delete_post_meta( $postinfo->ID, 'the_book');
		delete_post_meta( $postinfo->ID, 'nextpage_level');
		delete_post_meta( $postinfo->ID, 'toc_level');
		delete_post_meta( $postinfo->ID, 'toc_meta');
		delete_post_meta( $postinfo->ID, 'open_toc');
		delete_post_meta( $postinfo->ID, 'grid_toc');
		delete_post_meta( $postinfo->ID, 'book_author');
	}
}

// Запрещаем вывод оглавления в отрывке
function delete_toc_in_excerpt($text) {
	// Creates an excerpt if needed; and shortens the manual excerpt as well
	global $post;
	   $raw_excerpt = $text;
	   if ( '' == $text ) {
		  $text = get_the_content(''); 	
		  $text = bg_bpub_clear ($text);
		  $text = strip_shortcodes( $text );
		  $text = apply_filters('the_content', $text);
		  $text = str_replace(']]>', ']]&gt;', $text);
	   }

	$text = strip_tags($text);
	$excerpt_length = apply_filters('excerpt_length', 55);
	$excerpt_more = apply_filters('excerpt_more', ' ' . '[...]');
	$text = wp_trim_words( $text, $excerpt_length, $excerpt_more ); 

	return apply_filters('wp_trim_excerpt', $text, $raw_excerpt);
}


include_once ("inc/options.php");
/**************************************************************************
  Настраиваемые параметры плагина
***************************************************************************/
$options = get_option('bg_bpub_options');
// Пост является книгой по умолчанию
$is_book = isset ($options['default'])?$options['default']:""; 
// Уровень заголовков, по которым производить разбиение по страницам
$nextpage_level = $options['nextpage_level'];
// Максимальный уровень, до которого включать заголовки в оглавление
$toc_level = $options['toc_level'];
// Содержание на каждой странице
$toc_place = isset ($options['toc_place'])?$options['toc_place']:""; 
// Месторасположение имени автора в заголовке
$author_place = $options['author_place'];
// Не разбивать на страницы если символов в записи меньше этого значения
$pageless_limit = isset($options['pageless_limit']) ? $options['pageless_limit'] : 80000;
// Количество пунктов оглавления до которого (включительно) оно раскрыто по умолчанию
$auto_open_limit = isset($options['auto_open_limit']) ? $options['auto_open_limit'] : 7;


/**************************************************************************
  Функция обработки текста при сохранении поста
***************************************************************************/
function bg_bpub_save( $id ) {
	global $is_book, $nextpage_level, $toc_level;

	$post = get_post($id);
	if( isset($post) && in_array($post->post_type, apply_filters('bg_bpub_post_types', ['post', 'page']) ) ) { 	// убедимся что мы редактируем нужный тип поста
		if (is_admin() && (get_current_screen()->id == 'post' || get_current_screen()->id == 'page')) {	// убедимся что мы на нужной странице админки
			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE  ) return;					// пропустим если это автосохранение
			if ( ! current_user_can('edit_post', $id ) ) return;						// убедимся что пользователь может редактировать запись
		
			// Уровень заголовков, по которым производить разбиение по страницам
			if (get_post_meta($post->ID, 'nextpage_level',true))
				$nextpage_level = get_post_meta($post->ID, 'nextpage_level',true);
			// Максимальный уровень, до которого включать заголовки в оглавление
			if (get_post_meta($post->ID, 'toc_level',true))
				$toc_level = get_post_meta($post->ID, 'toc_level',true);

			$content = $post->post_content;
			// Удаляем ранее внесенные изменения
			$content = bg_bpub_clear ($content);
			// Добавляем разрывы страниц и оглавление
			if (get_post_meta($id, 'the_book',true)) $content = bg_bpub_proc ($content, $id);

			// Удаляем хук, чтобы не было зацикливания
			remove_action( 'save_post', 'bg_bpub_save' );

			// обновляем запись. В это время срабатывает событие save_post
			wp_update_post( array( 'ID' => $id, 'post_content' => $content ) );

			// Ставим хук обратно
			add_action( 'save_post', 'bg_bpub_save' );
		}
	}
}

/**************************************************************************
  Служебные глобальные переменные
***************************************************************************/
// Текущий порядковый номер заголовка
$headers = array(
	"h1" => 0,
	"h2" => 0,
	"h3" => 0,
	"h4" => 0,
	"h5" => 0,
	"h6" => 0);
// Порядковый номер страницы
$pagenum = 1;
// Оглавление
$table_of_contents = "";
$toc_meta = [];

function bg_bpub_prepare_title($str){
	$str = preg_replace('|<a.*href\=\"#\_ftn.*\/a>|uUi', '', $str);
	return strip_tags($str);
}

/**************************************************************************
	Функция разбора текста и формирования ссылок и оглавления
 **************************************************************************/
function bg_bpub_proc ($content, $post_id) {
	global $toc_place, $table_of_contents, $toc_meta, $nextpage_level, $toc_level;
	
	$toc_meta = [];	
	
	//error_log('bg_bpub_proc started ::: nextpage_level = ' . $nextpage_level . " ::: toc_level = " . $toc_level);
	if(get_post_meta($post_id, 'toc_level', true) < 2){
		delete_post_meta($post_id, 'toc_meta');
		
		return $content;
	}
	
	if(mb_strlen($content) <= $pageless_limit)	//Не разбиваем на страницы короткие тексты
		$nextpage_level = 1;
	
	// Ищем все заголовки
	$content = preg_replace_callback ('/<(h[1-6])(.*?)>(.*?)<\/\1>/i',
		function ($match) use($nextpage_level) {
			global $headers, $pagenum, $table_of_contents, $toc_level, $toc_meta;
			
			$level = (int) $match[1][1];			// Уровень заголовка от 1 до 6 (берётся из стоки типа 'h1')
			
			$headers['h'.$level]++;					// Увеличиваем текущий номер заголовка этого уровня
			for ($l=$level; $l<=6; $l++) {			// и сбрасываем нумерацию заголовков нижнего уровня
				$headers['h'.($l+1)] = 0;
			}
			
			//error_log('bg_bpub_proc ::: '.print_r($match, true). "\n" . $level . "\n" . print_r($headers, true));
			
			// Определяем место разбиения на страницы
			if ($level <= $nextpage_level && $headers['h'.$level] > 1) {
				$nextpage = "<!--nextpage-->";
				$pagenum++;
			} else
				$nextpage = "";
			
			$anchor = "";
				// Создаем оглавление
			if ($level <= $toc_level) {
				// Формируем имя якоря
				$name = 'ch';
				for ($l=0; $l<$level; $l++) $name.='_'.$headers['h'.($l+1)];
				// Создаем якорь
				$anchor = '<a id="'.$name.'"></a>';
				// Создаем оглавление
				if ($nextpage) {
					//$table_of_contents .= '<li><a class="bg_bpub_toc_'.$match[1].'" href="../'.$pagenum.'">'.strip_tags($match[3]).'</a></li>';
					$toc_meta[]= [
						'class'=> $match[1],
						'href' => $pagenum,
						'label' => bg_bpub_prepare_title($match[3]),
						];
				} else {
					//$table_of_contents .= '<li><a class="bg_bpub_toc_'.$match[1].'" href="../'.$pagenum.'/#'.$name.'">'.strip_tags($match[3]).'</a></li>';
					$toc_meta[]= [
						'class'=> $match[1],
						'href' => ( $pagenum>1 ? $pagenum.'/#' : '#' ) .$name,
						'label' => bg_bpub_prepare_title($match[3]),
						];
				}
			}	
			
			$res = $nextpage.'<'.$match[1].$match[2].'>'.$anchor.$match[3].'</'.$match[1].'>';
			
			//error_log('bg_bpub_proc replace ::: '.$match[0]. ' ---> '.$res);
			
			// Возвращаем заголовок с добавленными тегом новой страницы (в начале) и якорем (в конце)
			return $res;
		} ,$content);
		
	update_post_meta($post_id, 'toc_meta', serialize($toc_meta));
		
	//if ($table_of_contents) {
		/* translators: Summary in spoiler on a page */
		//$table_of_contents = '<div class="bg_bpub_toc"><details><summary><b>'.__('Table of contents', 'bg_bpub').'</b></summary><ul>'.$table_of_contents.'</ul></details></div>';

		// Оглавление на каждой странице, кроме первой
		/*
		if ($toc_place) {
			if (function_exists('bg_forreaders_proc'))
				$content = preg_replace ('/<!--nextpage-->/is', '<!--nextpage-->'.'[noread]'.$table_of_contents.'[/noread]', $content);	
			else
				$content = preg_replace ('/<!--nextpage-->/is', '<!--nextpage-->'.$table_of_contents, $content);	
		}
		
		// Оглавление на первой странице
		$content = 	preg_replace ('/href="\.\.\//is', 'href="', $table_of_contents).$content;
	}*/
	
	//error_log('bg_bpub_proc finished ::: '.$string = substr($content, 0, 150));
	
	return $content;
}

add_filter('the_content', 'bg_bpub_process_content_display');
function bg_bpub_process_content_display($content){
	global $post, $toc_place, $page, $pages, $numpages;
	
	if(!$post || !get_post_meta($post->ID, 'the_book',true))
		//return '<span style="display:none">not book</span>'.$content;
		return $content;
	
	$p_count = $numpages;
	$debug_mode = false;
	$debug = '';
	
	if(strpos($content, 'bg_bpub_toc') == false){	//если есть старое оглавление -- оставляем
		$debug = '<span style="display:none">no old toc</span>';
		// Удаляем оглавление старого формата
		//$content = preg_replace ('/<div class="bg_bpub_toc">(.*?)<\/div>/is', "", $content);	
		
		if(is_singular() && (!$page || $page == '' || $page == 1 || $toc_place == 1)){
		
			$toc_meta = unserialize(get_post_meta($post->ID, 'toc_meta', true));
			
			//$content = '<span style="display:none">toc_meta='.print_r($toc_meta, true).'</span>'.$content;
			
			if(!$toc_meta || $toc_meta==''){
				$debug = '<span style="display:none">empty toc meta</span>';
				$content = bg_bpub_clear ($content);
				bg_bpub_proc($post->post_content, $post->ID); //ещё не сформированы мета-данные с оглавлением, сформируем
				$toc_meta = unserialize(get_post_meta($post->ID, 'toc_meta', true));
				// мета-данные должны быть сгенерированы при сохранении к этому моменту
				//return '<span style="display:none">processed</span>'.$content;
			}
			
			if($toc_meta && $toc_meta!=='' && count($toc_meta)>1){	//пустое или состоящее из одного пункта оглавление не выводим
				$debug = '<span style="display:none">parsing toc meta: '.print_r($toc_meta, true).'</span>';
				
				$current_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			
				$link_pre = untrailingslashit(get_permalink($post));
				
				$table_of_contents = '';		
				
				if($p_count>1 && !isset($_GET['full_text']))
					$table_of_contents .= '<li class="btn-full-text"><a href="'.get_the_permalink().'?full_text=1">Полный текст</a></li>';
				
				foreach($toc_meta as $item){
					$target_url = ( $p_count>1 ? $link_pre . '/' : '' ) . $item['href'];
					$target_url = str_replace('/1/#','#', $target_url);	//fix old cached 1st page links
					$target_url = str_replace($page.'/#', '#', $target_url);
					$add_class = ($current_url == $target_url || $current_url == $target_url . '/') ? ' current' : '';
					$strippedTitle = strip_tags(do_shortcode($item['label']));
					$numeric = preg_match('~^[0-9IVXХCСML]{1,5}$~u', $strippedTitle);
					$table_of_contents .= '<li class="'.( $numeric ? 'numeric' : 'literal' ).'"><a class="bg_bpub_toc_'.$item['class'].$add_class.'" href="'.$target_url.'">'.$strippedTitle.'</a></li>';
				}
				
				$open = !empty(get_post_meta($post->ID, 'open_toc', true)) || (count($toc_meta) <= $auto_open_limit);
				$listClass = !empty(get_post_meta($post->ID, 'grid_toc', true)) ? ' grid' : '';
				
				$table_of_contents = apply_filters('bg_toc', '<div class="bg_bpub_toc" id="toc"><details'.( $open ? ' open="on"' : '').'><summary><b class="bpub-title">'.__('Table of contents', 'bg_bpub').'</b></summary><ul class="bg-bpub-list'.$listClass.'">'.$table_of_contents.'</ul><div class="vignette"></div></details></div>');
				
				$content = $table_of_contents.$content;
			}else{
				$debug = '<span style="display:none">toc meta ignored: '.print_r($toc_meta, true).'</span>';
			}
		}else{
			$debug = '<span style="display:none">toc meta ignored, not single or smth: '.is_singular().'|'.$page.'|'.$toc_place.'</span>';
		}
	}else{	//почистим мусор, который нагенерировался в БД при попытке создать мета-данные при старых оглавлениях
		delete_post_meta($post->ID, 'toc_meta');
		$debug = '<span style="display:none">old toc</span>';
	}
	
	return $debug_mode ? $debug.$content : $content;
}

/**************************************************************************
	Функция очистки текста от внесенных изменений
 **************************************************************************/
function bg_bpub_clear ($content) {
	
	// Удаляем оглавление
	$content = preg_replace ('/<div class="bg_bpub_toc">(.*?)<\/div>/is', "", $content);	
	$content = preg_replace ('/\[noread\]\s*\[\/noread\]/is', "", $content);	

	// Удаляем разбиение на страницы
	$content = preg_replace ('/<\!--nextpage-->/is', "", $content);	
	
	// Удаляем якори
	$content = preg_replace ('/<a name="ch_(.*?)"><\/a>/is', "", $content); // если сохранилось в старой версии	
	$content = preg_replace ('/<a id="ch_(.*?)"><\/a>/is', "", $content);	
	
	return $content;
}

/*****************************************************************************************
	Добавляем блок в боковую колонку на страницах редактирования страниц
	
******************************************************************************************/
add_action('admin_init', 'bg_bpub_extra_fields', 1);
// Создание блока
function bg_bpub_extra_fields() {
	/* translators: Meta box title */
    add_meta_box( 'bg_bpub_extra_fields', __('Book Publisher', 'bg_bpub'), 'bg_bpub_extra_fields_box_func', apply_filters('bg_bpub_post_types', ['post', 'page']), 'side', 'low'  );
}
// Добавление полей
function bg_bpub_extra_fields_box_func( $post ){
	global $is_book, $nextpage_level, $toc_level;
	
	wp_nonce_field( basename( __FILE__ ), 'bg_bpub_extra_fields_nonce' );
	// Дополнительное поле поста
	add_post_meta($post->ID, 'the_book', $is_book, true );
	add_post_meta($post->ID, 'nextpage_level', $nextpage_level, true );
	add_post_meta($post->ID, 'toc_level', $toc_level, true );
	add_post_meta($post->ID, 'book_author', "", true );
	
	$html = '<label><input type="checkbox" name="bg_bpub_the_book" id="bg_bpub_the_book"';
	$html .= (get_post_meta($post->ID, 'the_book',true)) ? ' checked="checked"' : '';
	/* translators: Сheckbox label (in Metabox)*/
	$html .= ' /> '.__('this post is book', 'bg_bpub').'</label><br>';

	/* translators: Label for input field  (in Metabox) */
	$html .= '<label>'.__('Header level for page break tags', 'bg_bpub').'<br>';
	$html .= '<input type="number" name="bg_bpub_nextpage_level" id="bg_bpub_nextpage_level" min="1" max="6"';
	$html .= ' value="'.get_post_meta($post->ID, 'nextpage_level',true).'" /></label><br>';

	/* translators: Label for input field  (in Metabox) */
	$html .= '<label>'.__('Header level for table of contents', 'bg_bpub').'<br>';
	$html .= '<input type="number" name="bg_bpub_toc_level" id="bg_bpub_toc_level" min="1" max="6"';
	$html .= ' value="'.get_post_meta($post->ID, 'toc_level',true).'" /></label><br>';

	/* translators: Label for input field  (in Metabox) */
	$html .= '<label>'.__('Book author', 'bg_bpub').'<br>';
	$html .= '<input type="text" name="bg_bpub_book_author" id="bg_bpub_book_author" size="35"';
	$html .= ' value="'.get_post_meta($post->ID, 'book_author',true).'" /></label><br>';
	
	$html .= '<br><label><input type="checkbox" name="bg_bpub_open_toc" id="bg_bpub_open_toc"';
	$html .= (get_post_meta($post->ID, 'open_toc',true)) ? ' checked="checked"' : '';
	/* translators: Сheckbox label (in Metabox)*/
	$html .= ' /> '.__('open TOC', 'bg_bpub').'</label><br>';
	
	$html .= '<br><label><input type="checkbox" name="bg_bpub_grid_toc" id="bg_bpub_grid_toc"';
	$html .= (get_post_meta($post->ID, 'grid_toc',true)) ? ' checked="checked"' : '';
	/* translators: Сheckbox label (in Metabox)*/
	$html .= ' /> '.__('grid TOC', 'bg_bpub').'</label><br>';

	echo $html;
}
// Сохранение значений произвольных полей при сохранении поста
add_action('save_post', 'bg_bpub_extra_fields_update', 0);
function bg_bpub_extra_fields_update( $post_id ){

	// проверяем, пришёл ли запрос со страницы с метабоксом
	if ( !isset( $_POST['bg_bpub_extra_fields_nonce'] )
	|| !wp_verify_nonce( $_POST['bg_bpub_extra_fields_nonce'], basename( __FILE__ ) ) ) return $post_id;
	// проверяем, является ли запрос автосохранением
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;
	// проверяем, права пользователя, может ли он редактировать записи
	if ( !current_user_can( 'edit_post', $post_id ) ) return $post_id;
	
	if (isset( $_POST['bg_bpub_the_book']) && $_POST['bg_bpub_the_book'] == 'on') {
		update_post_meta($post_id, 'the_book', $_POST['bg_bpub_the_book']);
		
		$nextpage_level = (int) sanitize_text_field($_POST['bg_bpub_nextpage_level']);
		if ($nextpage_level >0 && $nextpage_level <7)
			update_post_meta($post_id, 'nextpage_level', $nextpage_level);
		
		$toc_level = (int) sanitize_text_field($_POST['bg_bpub_toc_level']);
		if ($toc_level >0 && $toc_level <7)
			update_post_meta($post_id, 'toc_level', $toc_level);
		
		$book_author = sanitize_text_field($_POST['bg_bpub_book_author']); 
		$book_author = esc_html($book_author);
		update_post_meta($post_id, 'book_author', $book_author);
		
		update_post_meta($post_id, 'open_toc', $_POST['bg_bpub_open_toc']);
		update_post_meta($post_id, 'grid_toc', $_POST['bg_bpub_grid_toc']);
	} else {
		update_post_meta($post_id, 'the_book', '');
	}
	return $post_id;		
}


//LOAD FULL POST ON 'FULL_TEXT' REQUEST OR for short texts
add_action( 'the_post', function( $post )
{
    if ((mb_strlen($post->post_content) <= $pageless_limit) || (isset($_GET['full_text']) AND false !== strpos( $post->post_content, '<!--nextpage-->' )) ) 
    {
        $GLOBALS['pages']     = [ $post->post_content ];
        $GLOBALS['numpages']  = 0;
        $GLOBALS['multipage'] = false;
    }
}, 99 );

add_filter('body_class', function($classes){
	if(isset($_GET['full_text'] ))
		$classes[]='full-text';
	
	return $classes;
});