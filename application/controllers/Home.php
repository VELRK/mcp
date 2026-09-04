<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * TalkAIPilot marketing landing (CodeIgniter views from website templates).
 * Replaces the React SPA shell as the public homepage.
 */
class Home extends CI_Controller {

	public function index()
	{
		$this->output->set_output(render_template('home.html', 'home'));
	}
}
