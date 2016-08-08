<?php
class Shop extends CI_Controller
{
    private $_user;

    public function __construct()
    {
        parent::__construct();

        $this->_user = 'ゲストさん';
    }

    public function greet()
    {
        echo 'Hello, ' . $this->_user . '.';
    }

	public function index()
	{
		$this->load->view('welcome_message');
	}
}
?>
