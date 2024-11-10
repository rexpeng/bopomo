<?php

function inputForm() {
	echo "<form method=post>";
	echo "<input type=text name=u8>";
	echo "</form>";
}

// if( !function_exists('mb_str_split')){
//     function mb_str_split(  $string = '', $length = 1){
//         if(!empty($string)){ 
//             $split = array();
//             $mb_strlen = mb_strlen($string);
//             for($pi = 0; $pi < $mb_strlen; $pi += $length){
//                 $substr = mb_substr($string, $pi,$length);
//                 if( !empty($substr)){ 
//                     $split[] = $substr;
//                 }
//             }
//         }
//         return $split;
//     }
// }

function utf82Phonetic($str) {
	$u32Phonetic = json_decode(file_get_contents("u32Phonetic"), true);

    $words = preg_split('//u', $str, null, PREG_SPLIT_NO_EMPTY);// mb_str_split($str);

    $result = [];
    foreach($words as $word) {

		$u32 = mb_ord(mb_convert_encoding($word, 'UTF-32', 'UTF-8'), 'UTF-32');

		// echo $u32."<br>";

		if(isset($u32Phonetic[$u32])) {
			$phonetics = $u32Phonetic[$u32];

			// foreach($phonetics as $key => $phonetic) {
			// 	echo $key."=>".$phonetic."<br>";
			// }

			//$result[] = str_replace(["ˊ","ˇ","ˋ","˙"], "", $phonetics[0]);
			//$result[] = $phonetics[count($phonetics)-1];
			//$result[] = $phonetics[0];
			$result[] = $phonetics;
		} 
		// else {
		// 	$result[] = $word;
		// }
    }

    return $result;

}

function hex2RGB($hexStr, $returnAsString = false, $seperator = ',') {
    $hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $hexStr); // Gets a proper hex string
    $rgbArray = array();
    if (strlen($hexStr) == 6) { //If a proper hex code, convert using bitwise operation. No overhead... faster
        $colorVal = hexdec($hexStr);
        $rgbArray['red'] = 0xFF & ($colorVal >> 0x10);
        $rgbArray['green'] = 0xFF & ($colorVal >> 0x8);
        $rgbArray['blue'] = 0xFF & $colorVal;
    } elseif (strlen($hexStr) == 3) { //if shorthand notation, need some string manipulations
        $rgbArray['red'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
        $rgbArray['green'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
        $rgbArray['blue'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
    } else {
        return false; //Invalid hex color code
    }
    return $returnAsString ? implode($seperator, $rgbArray) : $rgbArray; // returns the rgb string or the associative array
}

function calculateTextBox($text,$fontFile,$fontSize,$fontAngle) {
    /************
    simple function that calculates the *exact* bounding box (single pixel precision).
    The function returns an associative array with these keys:
    left, top:  coordinates you will pass to imagettftext
    width, height: dimension of the image you have to create
    *************/
    //$rect = imagettfbbox($fontSize,$fontAngle,$fontFile,$text);
    $rect = imagettfbbox($fontSize,$fontAngle,$fontFile,$text);

   
    $minX = min(array($rect[0],$rect[2],$rect[4],$rect[6]));
    $maxX = max(array($rect[0],$rect[2],$rect[4],$rect[6]));
    $minY = min(array($rect[1],$rect[3],$rect[5],$rect[7]));
    $maxY = max(array($rect[1],$rect[3],$rect[5],$rect[7]));
   
    return array(
     "left"   => abs($minX),
     "top"    => abs($minY),
     "width"  => $maxX - $minX,
     "height" => $maxY - $minY,
     "box"    => $rect
    );
}

function toZYImage($zouyi, $width=600, $color='#626262', $font=1, $textSize=28, $nosign=false, $format=1) {
	//$width = 600;
	$width = max(300, $width);

	$height = 100000;

	$img = imagecreatetruecolor($width, $height);
    imageSaveAlpha($img, true);
	imageAlphaBlending($img, true);
	$white = imagecolorallocatealpha($img, 0xff, 0xff, 0xff, 127);
	$black = imagecolorallocatealpha($img, 0x0, 0x0, 0x0, 127);

	imagecolortransparent($img, $white);
	imagefill($img, 0, 0, $white);


	$rgb = hex2RGB($color);
	$textColor = imagecolorallocate($img, $rgb['red'], $rgb['green'], $rgb['blue']); 

	if($font == 1) {
		$fontname = realpath("./elffont-fern.otf");
	} else if($font == 2) {
		$fontname = realpath("./elffont-rock.otf");
	}

	$textSize = max(8, $textSize);
	

	$x = 0;
	$lines = [];
	$line = "";
	
	foreach($zouyi as $zy) {
		if(empty($zy)) {
			continue;
		}

		if($nosign) {
			$zy = str_replace(["ˊ","ˇ","ˋ","˙"], "", $zy);
		}

		$tmp = str_replace(["ˊ","ˇ","ˋ","˙"], "", $zy);
		$bbox = calculateTextBox($tmp, $fontname, $textSize, 0);

		$w = $bbox['width'];

		if(($x + $w) > $width) {
			
			$lines[] = $line;
			$line = "";
			$x = 0;
		} 
		$line .= $zy;
		$x += $w;
		
	}
	if(!empty($line)) {
		$lines[] = $line;
	}

	$wordSpace = 0;
	$maxWidth = 0;
	foreach($lines as $line) {
		//echo $line.'<br>';
		$tmp = str_replace(["ˊ","ˇ","ˋ","˙"], "", $line);
		$words = count(preg_split('//u', $tmp, null, PREG_SPLIT_NO_EMPTY));
		$bbox = calculateTextBox($tmp, $fontname, $textSize, 0);
		$maxWidth = max($bbox['width']+($words-1)*$wordSpace, $maxWidth);
	}

	$widthWord = [1=> ['ㄏ', 'ㄞ', 'ㄉ', 'ㄕ', 'ㄗ', 'ㄌ'], 2=>['ㄉ', 'ㄌ']];
	$x = 0;
	$y = 15;
	
	$lineSpace = 10;
	
	foreach($lines as $line) {
		$tmp = str_replace(["ˊ","ˇ","ˋ","˙"], "", $line);
		// $tmp = $line;
		$bbox = calculateTextBox($tmp, $fontname, $textSize, 0);
		switch($format) {
			case 1:
				$x = 0;
				break;
			case 3:
				$x = $maxWidth-$bbox['width'];
				break;
			case 2:
				$x = ($maxWidth-$bbox['width'])/2;
				break;
		}
		// echo $bbox['width'].'<br>';
		// imagerectangle($img, $x, $y, $x+$bbox['width'], $y+$bbox['height'], $textColor);

		// imagettftext($img, $textSize, 0, $x, $y+$bbox['top'], $textColor, $fontname, $tmp);
		
		// $y += $bbox['height']+$lineSpace;

		$words = preg_split('//u', $line, null, PREG_SPLIT_NO_EMPTY);
		foreach($words as $word) {
			//$word = trim($word);
			$wbox = calculateTextBox($word, $fontname, $textSize, 0);
			// print_r($wbox);
			// echo $wbox['width'];
			if(in_array($word, ["ˊ","ˇ","ˋ"])) {
				imagettftext($img, $textSize, 0, $x-$wbox['width'], $y+$wbox['top']-8, $textColor, $fontname, $word);
				continue;
			}
			if($word === '˙') {
				imagettftext($img, $textSize, 0, $x+$wbox['width'], $y+$wbox['top']-8, $textColor, $fontname, $word);
				continue;
			}
			$w = $wbox['width'];

			if(in_array($word, $widthWord[$font])) {
				$w -= ceil($textSize * 10 / 25);
			}
			
			// echo '/'.$w.'<br>';
			// imagerectangle($img, $x, $y, $x+$w, $y+$wbox['height'], $textColor);
			imagettftext($img, $textSize, 0, $x, $y+$wbox['top'], $textColor, $fontname, $word);
			$x += $w + $wordSpace;
		}

		$y += $bbox['height']+$lineSpace;
	}

	$cImgHeight = ceil($y+$bbox['height']/2);
	$cImg = imagecreatetruecolor($width, $cImgHeight);
	imageSaveAlpha($cImg, true);
	imageAlphaBlending($cImg, true);
	$white = imagecolorallocatealpha($cImg, 0xff, 0xff, 0xff, 127);
	imagecolortransparent($cImg, $white);
	imagefill($cImg, 0, 0, $white);
	$cX = floor(($width - $maxWidth) / 2);
	imagecopy($cImg, $img, $cX, floor($bbox['height']/2), 0, 0, $width, $cImgHeight);
	//$cImg = imagecrop($img, ['x'=>0, 'y'=>0, 'width'=>$width, 'height'=>$y+20]);
	//$cImg = imagecropauto($img); //, IMG_CROP_AUTO);
	//imageSaveAlpha($cImg, true);

	ob_start();
    imagepng($cImg);
    $outputImg = ob_get_clean();
    $outputBase64 = 'data:image/png;base64,'.base64_encode($outputImg);

    imagedestroy($cImg);
    imagedestroy($img);

    return $outputBase64;
}



// inputForm();


// $str = "";
// if(isset($_POST['u8'])) {
// 	$str = $_POST['u8'];
// }

// if(!empty($str)) {
// 	$zouyi = utf82Phonetic($str);
// 	echo $str." => ";

// 	// $kinds = 0;
// 	// foreach($zouyi as $zy) {
// 	// 	$kinds = max($zy, $kinds);
// 	// }

// 	foreach($zouyi as $zys) {
// 		echo $zys;
// 	}

// 	echo '<br><br>';


//     echo "<img src='".toZYImage($zouyi)."'>";

// }

if(isset($_POST['method'])) {
	$method = $_POST['method'];

	switch($method) {
		case 1:
			$text = $_POST['text'];
			$zouyi = utf82Phonetic($text);
			// foreach($zouyi as $zy) {
			// 	if(empty($zy)) {
			// 		continue;
			// 	}
			// 	echo $zy.",";
			// }
			echo json_encode($zouyi);
			break;
		case 2:
			$bopomo = explode(',', $_POST['bopomo']);
			$imgwidth = 600;
			if(isset($_POST['imgwidth'])) {
				$imgwidth = $_POST['imgwidth'];
			}
			$color = $_POST['imgcolor'];
			$font = $_POST['font'];
			$fontsize = $_POST['fontsize'];
			$nosign = filter_var($_POST['nosign'], FILTER_VALIDATE_BOOLEAN);
			$format = $_POST['format'];
			$b64img = toZYImage($bopomo, $imgwidth, $color, $font, $fontsize, $nosign, $format);
			echo "<img src='".$b64img."' style='max-width:100%;' alt='注音精靈文'>";
			break;
	}
}


?>