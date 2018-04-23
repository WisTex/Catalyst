<?php /** @file */

function photo_factory($data, $type = null) {
	$ph = null;


	$unsupported_types = array(
		'image/bmp',
		'image/vnd.microsoft.icon',
		'image/tiff',
		'image/svg+xml'
	);

	if($type && in_array(strtolower($type),$unsupported_types)) {
		logger('photo_factory: unsupported image type');
		return null;
	}

	$ignore_imagick = get_config('system', 'ignore_imagick');

	if(class_exists('Imagick') && !$ignore_imagick) {
		$v = Imagick::getVersion();
		preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', $v['versionString'], $m);
		if(version_compare($m[1],'6.6.7') >= 0) {
			require_once('include/photo/photo_imagick.php');
			$ph = new photo_imagick($data,$type);
		}
		else {
			// earlier imagick versions have issues with scaling png's
			// don't log this because it will just fill the logfile.
			// leave this note here so those who are looking for why 
			// we aren't using imagick can find it
		}
	}

	if(! $ph) {
		require_once('include/photo/photo_gd.php');
		$ph = new photo_gd($data,$type);
	}

	return $ph;
}




abstract class photo_driver {

	protected $image;
	protected $width;
	protected $height;
	protected $valid;
	protected $type;
	protected $types;

	abstract function supportedTypes();

	abstract function load($data,$type);

	abstract function destroy();

	abstract function setDimensions();

	abstract function getImage();

	abstract function doScaleImage($new_width,$new_height);

	abstract function rotate($degrees);

	abstract function flip($horiz = true, $vert = false);

	abstract function cropImage($max,$x,$y,$w,$h);

	abstract function cropImageRect($maxx,$maxy,$x,$y,$w,$h);

	abstract function imageString();

	abstract function clearexif();

	public function __construct($data, $type='') {
		$this->types = $this->supportedTypes();
		if (! array_key_exists($type,$this->types)){
			$type='image/jpeg';
		}
		$this->type = $type;
		$this->valid = false;
		$this->load($data,$type);
	}

	public function __destruct() {
		if($this->is_valid())
			$this->destroy();
	}

	public function is_valid() {
		return $this->valid;
	}

	public function getWidth() {
		if(!$this->is_valid())
			return FALSE;
		return $this->width;
	}

	public function getHeight() {
		if(!$this->is_valid())
			return FALSE;
		return $this->height;
	}


	public function saveImage($path) {
		if(!$this->is_valid())
			return FALSE;
		file_put_contents($path, $this->imageString());
	}


	public function getType() {
		if(!$this->is_valid())
			return FALSE;

		return $this->type;
	}

	public function getExt() {
		if(!$this->is_valid())
			return FALSE;

		return $this->types[$this->getType()];
	}

	/**
	 * @brief scale image
	 * int $max maximum pixel size in either dimension
	 * boolean $float_height - if true allow height to float to any length on tall images, 
	 *     constraining only the width
	 */

	public function scaleImage($max, $float_height = true) {
		if(!$this->is_valid())
			return FALSE;

		$width = $this->width;
		$height = $this->height;

		$dest_width = $dest_height = 0;

		if((! $width)|| (! $height))
			return FALSE;

		if($width > $max && $height > $max) {

			// very tall image (greater than 16:9)
			// constrain the width - let the height float.

			if(((($height * 9) / 16) > $width) && ($float_height)) {
				$dest_width = $max;
	 			$dest_height = intval(( $height * $max ) / $width);
			}

			// else constrain both dimensions

			elseif($width > $height) {
				$dest_width = $max;
				$dest_height = intval(( $height * $max ) / $width);
			}
			else {
				$dest_width = intval(( $width * $max ) / $height);
				$dest_height = $max;
			}
		}
		else {
			if( $width > $max ) {
				$dest_width = $max;
				$dest_height = intval(( $height * $max ) / $width);
			}
			else {
				if( $height > $max ) {

					// very tall image (greater than 16:9)
					// but width is OK - don't do anything

					if(((($height * 9) / 16) > $width) && ($float_height)) {
						$dest_width = $width;
	 					$dest_height = $height;
					}
					else {
						$dest_width = intval(( $width * $max ) / $height);
						$dest_height = $max;
					}
				}
				else {
					$dest_width = $width;
					$dest_height = $height;
				}
			}
		}
		$this->doScaleImage($dest_width,$dest_height);
	}

	public function scaleImageUp($min) {
		if(!$this->is_valid())
			return FALSE;


		$width = $this->width;
		$height = $this->height;

		$dest_width = $dest_height = 0;

		if((! $width)|| (! $height))
			return FALSE;

		if($width < $min && $height < $min) {
			if($width > $height) {
				$dest_width = $min;
				$dest_height = intval(( $height * $min ) / $width);
			}
			else {
				$dest_width = intval(( $width * $min ) / $height);
				$dest_height = $min;
			}
		}
		else {
			if( $width < $min ) {
				$dest_width = $min;
				$dest_height = intval(( $height * $min ) / $width);
			}
			else {
				if( $height < $min ) {
					$dest_width = intval(( $width * $min ) / $height);
					$dest_height = $min;
				}
				else {
					$dest_width = $width;
					$dest_height = $height;
				}
			}
		}
		$this->doScaleImage($dest_width,$dest_height);
	}


	public function scaleImageSquare($dim) {
		if(!$this->is_valid())
			return FALSE;
		$this->doScaleImage($dim,$dim);
	}


	/**
	 * @brief reads exif data from filename
	 */

	public function exif($filename) {


		if((! function_exists('exif_read_data')) 
			|| (! in_array($this->getType(), [ 'image/jpeg' , 'image/tiff'] ))) {
			return false;
		}

		/*
		 * PHP 7.2 allows you to use a stream resource, which should reduce/avoid
		 * memory exhaustion on large images. 
		 */

		if(version_compare(PHP_VERSION,'7.2.0') >= 0) {
			$f = @fopen($filename,'rb');
		}
		else {
			$f = $filename;
		}

		if($f) {
			return @exif_read_data($f,null,true);
		}

		return false;
	}

	/**
	 * @brief orients current image based on exif orientation information
	 */

	public function orient($exif) {

		if(! ($this->is_valid() && $exif)) {
			return false;
		}

		$ort = ((array_key_exists('IFD0',$exif)) ? $exif['IFD0']['Orientation'] : $exif['Orientation']);

		if(! $ort) {
			return false;
		}
		
		switch($ort) {
			case 1: // nothing
				break;
			case 2: // horizontal flip
				$this->flip();
				break;
			case 3: // 180 rotate left
				$this->rotate(180);
				break;
			case 4: // vertical flip
				$this->flip(false, true);
				break;
			case 5: // vertical flip + 90 rotate right
				$this->flip(false, true);
				$this->rotate(-90);
				break;
			case 6: // 90 rotate right
				$this->rotate(-90);
				break;
			case 7: // horizontal flip + 90 rotate right
				$this->flip();
				$this->rotate(-90);
				break;
			case 8:	// 90 rotate left
				$this->rotate(90);
				break;
			default:
				break;
		}

		return true;
	}


	public function save($arr) {

		if(! $this->is_valid()) {
			logger('attempt to store invalid photo.');
			return false;
		}

		$p = array();

		$p['aid'] = ((intval($arr['aid'])) ? intval($arr['aid']) : 0);
		$p['uid'] = ((intval($arr['uid'])) ? intval($arr['uid']) : 0);
		$p['xchan'] = (($arr['xchan']) ? $arr['xchan'] : '');
		$p['resource_id'] = (($arr['resource_id']) ? $arr['resource_id'] : '');
		$p['filename'] = (($arr['filename']) ? $arr['filename'] : '');
		$p['album'] = (($arr['album']) ? $arr['album'] : '');
		$p['imgscale'] = ((intval($arr['imgscale'])) ? intval($arr['imgscale']) : 0);
		$p['allow_cid'] = (($arr['allow_cid']) ? $arr['allow_cid'] : '');
		$p['allow_gid'] = (($arr['allow_gid']) ? $arr['allow_gid'] : '');
		$p['deny_cid'] = (($arr['deny_cid']) ? $arr['deny_cid'] : '');
		$p['deny_gid'] = (($arr['deny_gid']) ? $arr['deny_gid'] : '');
		$p['created'] = (($arr['created']) ? $arr['created'] : datetime_convert());
		$p['edited'] = (($arr['edited']) ? $arr['edited'] : $p['created']);
		$p['title'] = (($arr['title']) ? $arr['title'] : '');
		$p['description'] = (($arr['description']) ? $arr['description'] : '');
		$p['photo_usage'] = intval($arr['photo_usage']);
		$p['os_storage'] = intval($arr['os_storage']);			
		$p['os_path'] = $arr['os_path'];
		$p['os_syspath'] = ((array_key_exists('os_syspath',$arr)) ? $arr['os_syspath'] : '');
		$p['display_path'] = (($arr['display_path']) ? $arr['display_path'] : '');
		$p['width'] = (($arr['width']) ? $arr['width'] : $this->getWidth());
		$p['height'] = (($arr['height']) ? $arr['height'] : $this->getHeight());

		if(! intval($p['imgscale']))
			logger('save: ' . print_r($arr,true), LOGGER_DATA);

		$x = q("select id from photo where resource_id = '%s' and uid = %d and xchan = '%s' and imgscale = %d limit 1",
				dbesc($p['resource_id']),
				intval($p['uid']),
				dbesc($p['xchan']),
				intval($p['imgscale'])
		);
		if($x) {
			$r = q("UPDATE photo set
				aid = %d,
				uid = %d,
				xchan = '%s',
				resource_id = '%s',
				created = '%s',
				edited = '%s',
				filename = '%s',
				mimetype = '%s',
				album = '%s',
				height = %d,
				width = %d,
				content = '%s',
				os_storage = %d, 
				filesize = %d,
				imgscale = %d,
				photo_usage = %d,
				title = '%s',
				description = '%s',
				os_path = '%s',
				display_path = '%s',
				allow_cid = '%s',
				allow_gid = '%s',
				deny_cid = '%s',
				deny_gid = '%s'
				where id = %d",

				intval($p['aid']),
				intval($p['uid']),
				dbesc($p['xchan']),
				dbesc($p['resource_id']),
				dbesc($p['created']),
				dbesc($p['edited']),
				dbesc(basename($p['filename'])),
				dbesc($this->getType()),
				dbesc($p['album']),
				intval($p['height']),
				intval($p['width']),
				(intval($p['os_storage']) ? dbescbin($p['os_syspath']) : dbescbin($this->imageString())),
				intval($p['os_storage']),
				intval(strlen($this->imageString())),
				intval($p['imgscale']),
				intval($p['photo_usage']),
				dbesc($p['title']),
				dbesc($p['description']),
				dbesc($p['os_path']),
				dbesc($p['display_path']),
				dbesc($p['allow_cid']),
				dbesc($p['allow_gid']),
				dbesc($p['deny_cid']),
				dbesc($p['deny_gid']),
				intval($x[0]['id'])
			);
		}
		else {
			$r = q("INSERT INTO photo
				( aid, uid, xchan, resource_id, created, edited, filename, mimetype, album, height, width, content, os_storage, filesize, imgscale, photo_usage, title, description, os_path, display_path, allow_cid, allow_gid, deny_cid, deny_gid )
				VALUES ( %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, '%s', %d, %d, %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )",
				intval($p['aid']),
				intval($p['uid']),
				dbesc($p['xchan']),
				dbesc($p['resource_id']),
				dbesc($p['created']),
				dbesc($p['edited']),
				dbesc(basename($p['filename'])),
				dbesc($this->getType()),
				dbesc($p['album']),
				intval($p['height']),
				intval($p['width']),
				(intval($p['os_storage']) ? dbescbin($p['os_syspath']) : dbescbin($this->imageString())),
				intval($p['os_storage']),
				intval(strlen($this->imageString())),
				intval($p['imgscale']),
				intval($p['photo_usage']),
				dbesc($p['title']),
				dbesc($p['description']),
				dbesc($p['os_path']),
				dbesc($p['display_path']),
				dbesc($p['allow_cid']),
				dbesc($p['allow_gid']),
				dbesc($p['deny_cid']),
				dbesc($p['deny_gid'])
			);
		}
		logger('photo save ' . $p['imgscale'] . ' returned ' . intval($r));
		return $r;
	}

}








/**
 * Guess image mimetype from filename or from Content-Type header
 *
 * @arg $filename string Image filename
 * @arg $headers string Headers to check for Content-Type (from curl request)
 */

function guess_image_type($filename, $headers = '') {
//	logger('Photo: guess_image_type: '.$filename . ($headers?' from curl headers':''), LOGGER_DEBUG);
	$type = null;
	if ($headers) {

		$hdrs=array();
		$h = explode("\n",$headers);
		foreach ($h as $l) {
			list($k,$v) = array_map("trim", explode(":", trim($l), 2));
			$hdrs[$k] = $v;
		}
		logger('Curl headers: '.var_export($hdrs, true), LOGGER_DEBUG);
		if (array_key_exists('Content-Type', $hdrs))
			$type = $hdrs['Content-Type'];
	}
	if (is_null($type)){

		$ignore_imagick = get_config('system', 'ignore_imagick');
		// Guessing from extension? Isn't that... dangerous?
		if(class_exists('Imagick') && file_exists($filename) && is_readable($filename) && !$ignore_imagick) {
			$v = Imagick::getVersion();
			preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', $v['versionString'], $m);
			if(version_compare($m[1],'6.6.7') >= 0) {
				/**
				 * Well, this not much better,
				 * but at least it comes from the data inside the image,
				 * we won't be tricked by a manipulated extension
			 	*/
				$image = new Imagick($filename);
				$type = $image->getImageMimeType();
			}
			else {
				// earlier imagick versions have issues with scaling png's
				// don't log this because it will just fill the logfile.
				// leave this note here so those who are looking for why 
				// we aren't using imagick can find it
			}
		}

		if(is_null($type)) {
			$ext = pathinfo($filename, PATHINFO_EXTENSION);
			$ph = photo_factory('');
			$types = $ph->supportedTypes();
			foreach($types as $m => $e) {
				if($ext === $e) {
					$type = $m;
				}
			}
		}

		if(is_null($type) && (strpos($filename,'http') === false)) {
			$size = getimagesize($filename);
			$ph = photo_factory('');
			$types = $ph->supportedTypes();
			$type = ((array_key_exists($size['mime'], $types)) ? $size['mime'] : 'image/jpeg');
		}
		if(is_null($type)) {
			if(strpos(strtolower($filename),'jpg') !== false)
				$type = 'image/jpeg';
			elseif(strpos(strtolower($filename),'jpeg') !== false)
				$type = 'image/jpeg';
			elseif(strpos(strtolower($filename),'gif') !== false)
				$type = 'image/gif';
			elseif(strpos(strtolower($filename),'png') !== false)
				$type = 'image/png';
		}

	}
	logger('Photo: guess_image_type: filename = ' . $filename . ' type = ' . $type, LOGGER_DEBUG);
	return $type;

}


function delete_thing_photo($url,$ob_hash) {

	$hash = basename($url);
	$hash = substr($hash,0,strpos($hash,'-'));

	// hashes should be 32 bytes. 

	if((! $ob_hash) || (strlen($hash) < 16))
		return;	

	$r = q("delete from photo where xchan = '%s' and photo_usage = %d and resource_id = '%s'",
		dbesc($ob_hash),
		intval(PHOTO_THING),
		dbesc($hash)
	);
	
}



function import_xchan_photo($photo,$xchan,$thing = false) {

	$flags = (($thing) ? PHOTO_THING : PHOTO_XCHAN);
	$album = (($thing) ? 'Things' : 'Contact Photos');

	logger('import_xchan_photo: updating channel photo from ' . $photo . ' for ' . $xchan, LOGGER_DEBUG);

	if($thing)
		$hash = photo_new_resource();
	else {
		$r = q("select resource_id from photo where xchan = '%s' and photo_usage = %d and imgscale = 4 limit 1",
			dbesc($xchan),
			intval(PHOTO_XCHAN)
		);
		if($r) {
			$hash = $r[0]['resource_id'];
		}
		else {
			$hash = photo_new_resource();
		}
	}

	$photo_failure = false;
	$img_str = '';

	if($photo) {
		$filename = basename($photo);

		$result = z_fetch_url($photo,true);

		if($result['success']) {
			$img_str = $result['body'];
			$type = guess_image_type($photo, $result['header']);

			$h = explode("\n",$result['header']);
			if($h) {
				foreach($h as $hl) {
					if(stristr($hl,'content-type:')) {
						if(! stristr($hl,'image/')) {
							$photo_failure = true;
						}
					}
				}
			}
		}
	}
	else {
		$photo_failure = true;
	}

	if(! $photo_failure) {
		$img = photo_factory($img_str, $type);
		if($img->is_valid()) {
			$width = $img->getWidth();
			$height = $img->getHeight();
	
			if($width && $height) {
				if(($width / $height) > 1.2) {
					// crop out the sides
					$margin = $width - $height;
					$img->cropImage(300,($margin / 2),0,$height,$height);
				}
				elseif(($height / $width) > 1.2) {
					// crop out the bottom
					$margin = $height - $width;
					$img->cropImage(300,0,0,$width,$width);

				}
				else {
					$img->scaleImageSquare(300);
				}

			}
			else 
				$photo_failure = true;

			$p = array('xchan' => $xchan,'resource_id' => $hash, 'filename' => basename($photo), 'album' => $album, 'photo_usage' => $flags, 'imgscale' => 4);

			$r = $img->save($p);

			if($r === false)
				$photo_failure = true;

			$img->scaleImage(80);
			$p['imgscale'] = 5;
	
			$r = $img->save($p);

			if($r === false)
				$photo_failure = true;
	
			$img->scaleImage(48);
			$p['imgscale'] = 6;
	
			$r = $img->save($p);

			if($r === false)
				$photo_failure = true;

			$photo = z_root() . '/photo/' . $hash . '-4';
			$thumb = z_root() . '/photo/' . $hash . '-5';
			$micro = z_root() . '/photo/' . $hash . '-6';
		}
		else {
			logger('import_xchan_photo: invalid image from ' . $photo);	
			$photo_failure = true;
		}
	}
	if($photo_failure) {
		$photo = z_root() . '/' . get_default_profile_photo();
		$thumb = z_root() . '/' . get_default_profile_photo(80);
		$micro = z_root() . '/' . get_default_profile_photo(48);
		$type = 'image/png';
	}

	return(array($photo,$thumb,$micro,$type,$photo_failure));

}

function import_channel_photo_from_url($photo,$aid,$uid) {

	if($photo) {
		$filename = basename($photo);

		$result = z_fetch_url($photo,true);

		if($result['success']) {
			$img_str = $result['body'];
			$type = guess_image_type($photo, $result['header']);

			$h = explode("\n",$result['header']);
			if($h) {
				foreach($h as $hl) {
					if(stristr($hl,'content-type:')) {
						if(! stristr($hl,'image/')) {
							$photo_failure = true;
						}
					}
				}
			}
		}
	}
	else {
		$photo_failure = true;
	}

	import_channel_photo($img_str,$type,$aid,$uid);

	return $type;
}


function import_channel_photo($photo,$type,$aid,$uid) {

	logger('import_channel_photo: importing channel photo for ' . $uid, LOGGER_DEBUG);

	$hash = photo_new_resource();

	$photo_failure = false;


	$filename = $hash;

	$img = photo_factory($photo, $type);
	if($img->is_valid()) {

		$img->scaleImageSquare(300);

		$p = array('aid' => $aid, 'uid' => $uid, 'resource_id' => $hash, 'filename' => $filename, 'album' => t('Profile Photos'), 'photo_usage' => PHOTO_PROFILE, 'imgscale' => 4);

		$r = $img->save($p);

		if($r === false)
			$photo_failure = true;

		$img->scaleImage(80);
		$p['imgscale'] = 5;

		$r = $img->save($p);

		if($r === false)
			$photo_failure = true;

		$img->scaleImage(48);
		$p['imgscale'] = 6;

		$r = $img->save($p);

		if($r === false)
			$photo_failure = true;

	}
	else {
		logger('import_channel_photo: invalid image.');
		$photo_failure = true;
	}

	//return(($photo_failure)? false : true);

	if($photo_failure)
		return false;
	else
		return $hash;

}
