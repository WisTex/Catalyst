<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

class Safe extends Controller {

	function init() {

		$x = get_safemode();
		if ($x) {
			$_SESSION['safemode'] = 0;
		}
		else {
			$_SESSION['safemode'] = 1;
		}
		goaway(z_root());
	}



}