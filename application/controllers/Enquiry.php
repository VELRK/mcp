<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Enquiry extends CI_Controller {

	public function index()
	{
		$this->output->set_output(render_template('contact.html', 'enquiry'));
	}

	public function save()
	{
		save_site_enquiry('enquiry');
	}
}
