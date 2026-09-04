<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Contact extends CI_Controller {

	public function index()
	{
		$this->output->set_output(render_template('contact.html', 'contact'));
	}

	public function save()
	{
		save_site_enquiry('contact');
	}

	public function submit()
	{
		$this->save();
	}
}
