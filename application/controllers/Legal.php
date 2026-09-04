<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Legal extends CI_Controller {

	public function terms()
	{
		$this->output->set_output(render_template('contact.html', 'terms'));
	}

	public function privacy()
	{
		$this->output->set_output(render_template('contact.html', 'privacy'));
	}
}
