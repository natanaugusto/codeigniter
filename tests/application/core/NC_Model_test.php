<?php

class NC_Model_test extends CI_TestCase {
	public function setUp()
	{
		$loader = $this->ci_core_class('loader');
		$this->load = new $loader();
		$this->ci_obj = $this->ci_instance();
		$this->ci_set_core_class('model', 'NC_Model');

		$model_code =<<<MODEL
<?php
class Test_model extends NC_Model {
	protected \$table = "tabletest";
}
MODEL;

		$this->ci_vfs_create('Test_model', $model_code, $this->ci_app_root, 'models');
		$this->load->model('test_model');
	}

	public function test_get() 
	{
		$this->assertEquals('tabletest', $this->ci_obj->test_model->get('table'));
	}
}
