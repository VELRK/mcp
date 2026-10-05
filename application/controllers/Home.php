<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * TalkAIPilot marketing landing (CodeIgniter views from website templates).
 * Replaces the React SPA shell as the public homepage.
 */
class Home extends CI_Controller {

	public function index()
	{
		$this->load->view('website/home_landing');
	}
}
