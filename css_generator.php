<?php
if (!extension_loaded('gd')) {
	exit ("\n".'Missing dynamic library \'GD\''."\n.");    
}

$options = getopt("ri:s:", ['recursive', 'output-image::', 'output-style::']);
$recursive = (isset($options['recursive'])) ? TRUE : (isset($options['r'])) ? TRUE : FALSE; // Check recursion options --> TRUE is / FALSE isn't
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$dir = (is_dir($argv[count($argv) - 1])) ? $argv[count($argv) - 1] : NULL; // Check last arg is directory
$ims = array();


function define_ims($recursive, $dir, $ims, $finfo){ // Create array with all png paths and final sprite size
	static $h;
	static $w;

	if(!is_null($dir) && is_readable($dir)){
		/*----------																				BEGIN FOREACH here -- Push in array paths of each image PNG 				*/
		foreach (glob($dir.DIRECTORY_SEPARATOR."*") as $filename) {

			if (finfo_file($finfo, $filename) === "image/png"){
				list($tmp_w, $tmp_h) = getimagesize($filename);
				$w = ($tmp_w >= $w) ? $tmp_w : $w;
				$h += $tmp_h;
			}
			else if ($recursive === TRUE &&	 is_dir($filename)){
				$ims = define_ims($recursive, $filename, $ims, $finfo);
			}

			if (finfo_file($finfo, $filename) === "image/png"){
				array_push($ims, $filename);
			}
		}
		/*----------																				END FOREACH here 				*/		
	}
	/*----------																				Finally push final sprite size 				*/
	array_push($ims, $w);
	array_push($ims, $h);
	/*----------																				Unset list for free memory				*/
	unset($h);
	unset($w);
	unset($tmp_h);
	unset($tmp_w);
	return ($ims);
}

function my_sprite($options, $dir, $ims){ //Generate final PNG sprite
	$im_name = 'sprite.png';	// Default name
	if(isset($options['i']) && !is_bool($options['i']) && !is_dir($options['i'])){ // check -i name defined 
		$im_name = $options['i'];
	}
	else if(isset($options['output-image']) && !is_bool($options['output-image']) && !is_dir($options['output-image'])){	// check output-image name defined
		$im_name = $options['output-image'];
	}

	$w = isset($ims[count($ims) - 2]) ? $ims[count($ims) - 2] : NULL;
	$h = isset($ims[count($ims) - 1]) ? $ims[count($ims) - 1] : NULL;
	$pos_h = 0;
	if (!is_null($w) && !is_null($h)){//			Create image with final size below
		$destimg = @imagecreatetruecolor($w, $h);
		imagecolortransparent($destimg, 0);
	}
	/*----------																				BEGIN FOREACH here 	-- Generating the final sprite with all PNGs			*/
	foreach ($ims as $key => $filename) {
		if (file_exists($filename)){
			list($tmp_w, $tmp_h) = getimagesize($filename);//	Define size of each PNG
			$src = @imagecreatefrompng($filename);
			@imagecopymerge($destimg, imagecreatefrompng($filename), 0, $pos_h, 0, 0, $tmp_w, $tmp_h, 100);//	Coopy each PNG in final sprite
			imagedestroy($src);// 		Free memory after each copy
			$pos_h += $tmp_h;//		Set new position for next PNG
		}
	}
	/*----------																				END FOREACH HERE 				*/
	if (isset($destimg)){
		@imagepng($destimg, $im_name);//	Finally save sprite
		imagedestroy($destimg);
		unset($destimg);
	}
	array_push($ims, $im_name);//	Push in array name image for CSS generation 
	return ($ims);
}

function my_css(&$options, $ims, $finfo){//				Generate final CSS Stylesheet
	if (file_exists(array_reverse($ims)[0])){
		$pos_height = 0;
		$final_css = NULL;
		$css = NULL;
		$char_to_replace = [' ', '(', ')', '.', '..', '/', '\\', '{', '}', ',', ';', ':', '@', '#', '"', '\'', '<', '>', '?', '=', '+', '~', '*', '|', '!', '¦', '`', '¬', '&', '^'];//	Set char to replace for valid classname
		$stylesheet_name = 'style.css';//	Set default name

		if(isset($options['s']) && !is_bool($options['s']) && !is_dir($options['s'])){// if defined set -s name below
			$stylesheet_name = $options['s'];
		}
		else if(isset($options['output-style']) && !is_bool($options['output-style']) && !is_dir($options['output-style'])){// if defined set -output-style name below
			$stylesheet_name = $options['output-style'];
		}
		/*----------																				BEGIN FOREACH here -- Concatenation of css string to push in css file 				*/
		foreach ($ims as $key => $filename) {
			if (file_exists($filename)){
				if (finfo_file($finfo, $filename) == "image/png"){
					$final_css .= '.'.str_replace($char_to_replace, '', $filename).', ';
					list($width, $height) = getimagesize($filename);// Set height, width for css rules and concatenate string below
					$css .= '/*'."\t".'To use this CSS style please add in your html balise : class="'.str_replace($char_to_replace, '', $filename).'" Example : <div class="'.str_replace($char_to_replace, '', $filename).'"></div>'."\t".'*/'."\n.".str_replace($char_to_replace, '', $filename)."{\n\tbackground-position: -0px -".$pos_height."px;\n\twidth: ".$width."px;\n\theight: ".$height."px;\n}\n\n";
					$pos_height += $height;// Set new heught for next selector
				}
			}
		}
		$final_css = substr($final_css, 0, -2);
		$final_css .= "{\n\t".'background: url(\''.array_reverse($ims)[0].'\') no-repeat;'."\n}\n\n";
		$final_css .= $css;
		/*----------																				END FOREACH here 				*/
		$css_file = fopen($stylesheet_name, 'a+');
		file_put_contents($stylesheet_name, substr($final_css, 0, -2));
		fclose($css_file);
	}
	/*----------																				BEGIN unset list for free memory 				*/
	unset($stylesheet_name);
	unset($css);
	unset($pos_height);
	unset($char_to_replace);
	unset($ims);
	unset($height);
	unset($width);
	unset($css_file);
}

my_css($options, my_sprite($options, $dir, define_ims($recursive, $dir, $ims, $finfo)), $finfo);
//					Final unset list for free memory
unset($finfo);
unset($options);
unset($recursive);
unset($dir);
?>
