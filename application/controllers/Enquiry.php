<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Enquiry extends CI_Controller {

	public function index()
	{
		$this->output->set_output(render_template('contact.html', 'enquiry'));
	}
}
