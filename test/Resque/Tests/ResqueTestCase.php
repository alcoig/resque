<?php

namespace Resque\Tests;

use PHPUnit_Framework_TestCase;

/**
 * Resque test case class. Contains setup and teardown methods.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class ResqueTestCase extends PHPUnit_Framework_TestCase
{
	protected $resque;
	protected $redis;

	public function setUp()
	{
        parent::setUp();

		$this->redis = new \Credis_Client('localhost');
		$this->redis->flushDb();
	}
}
