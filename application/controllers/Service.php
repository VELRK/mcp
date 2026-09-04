<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Service extends CI_Controller {

	public function index()
	{
		$this->output->set_output(render_template('service.html', 'service'));
	}

	public function detail($slug = '')
	{
		$slug = strtolower(trim((string) $slug));
		$services = site_services();

		if ($slug === '' || !isset($services[$slug])) {
			show_404();
			return;
		}

		$file = 'services'.DIRECTORY_SEPARATOR.$slug.'.html';
		$this->output->set_output(render_template($file, 'service', $slug));
	}
}
