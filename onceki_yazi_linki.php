<?php
/**
 * @package Önceki Yazı Link
 * @version 1.3
 */
/*
Plugin Name: Önceki Yazı Link
Plugin URI: http://www.seohocasi.com/wordpress-onceki-yazi-linki-seo-eklentisi/
Description: Her yazının sonuna bir önceki yazının linkini ekleyerek blogunuzun SEO konusundaki çalışmalarına fayda sağlayan bir eklenti.
Author: SEO Hocası
Version: 1.3
*/

function seohocasi_onceki_yazi($icerik = null) {
	global $post, $wpdb;
	if(!is_single()) return $icerik;
	$ayarlar = seohocasi_oy_ayarlar();
	$pasif_idler = explode(',', $ayarlar['pasif_id']);
	$pasif_idler = array_map('trim', $pasif_idler);
	if(isset($ayarlar['pasif_id']) && in_array($post->ID, $pasif_idler)) return $icerik;

	if($ayarlar['kategori_ayrimi']) {
		$kategoriler[0] = 0;

		$_kategoriler = get_the_category();
		foreach($_kategoriler as $kategori) {
			$kategoriler[] = $kategori->cat_ID;
		}

		$onceki_yazi = $wpdb->get_row('SELECT * FROM ' . $wpdb->posts . ' WHERE post_status = "publish" AND post_type = "post" AND ID < ' . $post->ID . ' AND ID IN (SELECT object_id FROM '.$wpdb->term_relationships.' WHERE term_taxonomy_id IN ('.implode(', ', $kategoriler).')) ORDER BY post_date DESC LIMIT 1');
	} else {
		$onceki_yazi = $wpdb->get_row('SELECT * FROM ' . $wpdb->posts . ' WHERE post_status = "publish" AND post_type = "post" AND ID < ' . $post->ID . ' ORDER BY post_date DESC LIMIT 1');
	}
	
	if($onceki_yazi) {
		$etiketler = array();
		$_etiketler = (array) get_the_tags($onceki_yazi->ID);
		$say = 0;
		foreach($_etiketler as $etiket) {
			$etiketler[] = $etiket->name;
			$say++;
			if($say == 3) break;
		}

		switch (count($etiketler)){
		case 1:
			$etiketler = $etiketler[0];
			break;
		case 2:
			$etiketler = $etiketler[0] . ' ve ' . $etiketler[1];
			break;
		case 3:
			$etiketler = $etiketler[0] . ', ' .$etiketler[1] . ' ve ' . $etiketler[2];
		}

		if(count($etiketler) == 0) {
			$metin = strtr($ayarlar['etiketsiz_metin'], array(
				'%BASLIK%' => $onceki_yazi->post_title,
				'%LINK%' => get_permalink($onceki_yazi->ID)
			));
			$icerik .= $metin;
		} else {
			$metin = strtr($ayarlar['etiketli_metin'], array(
				'%BASLIK%' => $onceki_yazi->post_title,
				'%LINK%' => get_permalink($onceki_yazi->ID),
				'%ETIKET%' => $etiketler
			));
			$icerik .= $metin;
		}
	}

	return $icerik;
}

function seohocasi_oy_ayarlar($init = false) {
	$eklentiAyarlar = array(
		'etiketsiz_metin' => '<p class="onceki_yazi">Bir önceki yazımız olan <a title="%BASLIK%" href="%LINK%">%BASLIK%</a> başlıklı makalemizi de okumanızı öneririz.</p>',
		'etiketli_metin' => '<p class="onceki_yazi">Bir önceki yazımız olan <a title="%BASLIK%" href="%LINK%">%BASLIK%</a> başlıklı makalemizde %ETIKET% hakkında bilgiler verilmektedir.</p>',
		'pasif_id' => '',
		'kategori_ayrimi' => true
	);

	$ayarlar = get_option('seohocasi_oncekiyazi');
	if(!empty($ayarlar) && $init == false) {
		foreach($ayarlar as $key => $option) {
			$eklentiAyarlar[$key] = $option;
		}
	} else {
		update_option('seohocasi_oncekiyazi', $eklentiAyarlar);
	}

	return $eklentiAyarlar;
}

function seohocasi_oy_init() {
	seohocasi_oy_ayarlar(true);
}

function seohocasi_oy_adminmenu() {
	add_options_page('Önceki Yazı Linki', 'Önceki Yazı Linki', 9, basename(__FILE__), 'seohocasi_oy_panel');
}

function seohocasi_oy_panel() {
	$ayarlar = seohocasi_oy_ayarlar();
	
	if(isset($_POST['update_seohocasi_oy'])) {
		$ayarlar['etiketli_metin'] = stripslashes($_POST['etiketli_metin']);
		$ayarlar['etiketsiz_metin'] = stripslashes($_POST['etiketsiz_metin']);
		$ayarlar['pasif_id'] = stripslashes($_POST['pasif_id']);
		$ayarlar['kategori_ayrimi'] = isset($_POST['kategori_ayrimi']) ? true : false;

		update_option('seohocasi_oncekiyazi', $ayarlar);
?><div class="updated"><p><strong>Ayarlar kaydedildi</strong></p></div><?php
	}
?>
<div class=wrap>
	<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
		<h2>SEO Hocası - Önceki Yazı Linki Eklentisi</h2>
		<h3>Etiketli metin taslağı</h3>
		<p>%BASLIK% : Önceki yazının başlığı<br/>%LINK% : Önceki yazının linki<br/>%ETIKET% : Önceki yazının etiketleri</p>
		<textarea name="etiketli_metin" style="width: 80%; height: 70px;"><?php echo $ayarlar['etiketli_metin'] ?></textarea>

		<h3>Etiketsiz metin taslağı</h3>
		<p>%BASLIK% : Önceki yazının başlığı<br/>%LINK% : Önceki yazının linki</p>
		<textarea name="etiketsiz_metin" style="width: 80%; height: 70px;"><?php echo $ayarlar['etiketsiz_metin'] ?></textarea>

		<h3>Önceki yazı önerisi kategoriye göre mi yapılsın?</h3>
		<input type="checkbox" name="kategori_ayrimi" value="1" <?php if($ayarlar['kategori_ayrimi']) echo ' checked=true' ?> /> Evet
		<h3>Pasif edilecek yazıların ID'leri</h3>
		<p>Birden fazla ID girmek için virgül ile ayırın</p>
		<textarea name="pasif_id" style="width: 80%; height: 70px;"><?php echo $ayarlar['pasif_id'] ?></textarea>
		<div class="submit">
		<input type="submit" name="update_seohocasi_oy" value="<?php _e('Ayarları Kaydet') ?>" /></div>
	</form>
	<p><a href="http://www.seohocasi.com/wordpress-onceki-yazi-linki-seo-eklentisi/" target="_blank">Eklenti sayfası</a> - <a href="http://www.seohocasi.com" target="_blank">SEO Hocası</a></p>
 </div>
<?php
}

add_action('admin_menu', 'seohocasi_oy_adminmenu');
add_action('onceki_yazi_linki.php', 'seohocasi_oy_init');
add_filter('the_content', 'seohocasi_onceki_yazi');
?>
