<?php

class foo {
	function something() {
		echo __CLASS__." something\n";
		$this->somethingElse();
	}

	function somethingElse() {
		echo __CLASS__." somethingElse\n";
	}
}

class foobar extends foo {
	function somethingElse() {
		echo __CLASS__." somethingElse\n";
	}
}

$foo = new foobar();

$foo->something();

?>