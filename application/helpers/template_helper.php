<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function site_services()
{
	return array(
		'image-processing'      => 'WhatsApp AI',
		'actionable-insights'   => 'Social Media Automation',
		'data-stored'           => 'AI Chatbot',
		'data-processing'       => 'Lead Automation',
		'deep-learning'         => 'Ecommerce Automation',
		'ai-for-cloud-services' => 'Voice AI',
		'virtual-reality'       => 'Integrations',
		'machine-learning'      => 'AI Sales Agent',
		'robotic-automation'    => 'Automation Builder',
	);
}

function site_service_summaries()
{
	return array(
		'image-processing'      => '24/7 WhatsApp replies, lead qualification, product links, payments and human handoff.',
		'actionable-insights'   => 'Turn Instagram, Facebook and YouTube comments into conversations and CRM leads.',
		'data-stored'           => 'An AI agent that understands your products, FAQs, pricing, availability and policies.',
		'data-processing'       => 'Capture, score and follow up every lead automatically so none are missed.',
		'deep-learning'         => 'Search, recommend, cart and checkout products inside WhatsApp.',
		'ai-for-cloud-services' => 'Inbound and outbound AI voice for qualification, reminders and support.',
		'virtual-reality'       => 'Connect WhatsApp, Instagram, Facebook, Shopify, CRM, calendar and payments.',
		'machine-learning'      => 'Greeting, qualification, objection handling, booking and escalation — 24/7.',
		'robotic-automation'    => 'Build no-code workflows: trigger, AI decision, follow-up and CRM update.',
	);
}

function site_menu_items($current = 'home')
{
	$base = base_url();
	$current_cls = ' current-menu-item current_page_item';

	$home_cls = ($current === 'home') ? $current_cls : '';
	$about_cls = ($current === 'about') ? $current_cls : '';
	$service_cls = ($current === 'service') ? $current_cls : '';
	$contact_cls = ($current === 'contact') ? $current_cls : '';

	$html  = '<li class="menu-item menu-item-home'.$home_cls.'"><a href="'.htmlspecialchars($base, ENT_QUOTES, 'UTF-8').'"'.($current === 'home' ? ' aria-current="page"' : '').'>Home</a></li>';
	$html .= '<li class="menu-item'.$about_cls.'"><a href="'.htmlspecialchars($base.'about', ENT_QUOTES, 'UTF-8').'"'.($current === 'about' ? ' aria-current="page"' : '').'>About</a></li>';
	$html .= '<li class="menu-item'.$service_cls.'"><a href="'.htmlspecialchars($base.'service', ENT_QUOTES, 'UTF-8').'"'.($current === 'service' ? ' aria-current="page"' : '').'>Service</a></li>';
	$html .= '<li class="menu-item'.$contact_cls.'"><a href="'.htmlspecialchars($base.'contact', ENT_QUOTES, 'UTF-8').'"'.($current === 'contact' ? ' aria-current="page"' : '').'>Contact</a></li>';

	return $html;
}

function site_footer_quick_links()
{
	$base = base_url();
	return '<li class="menu-item"><a href="'.htmlspecialchars($base.'about', ENT_QUOTES, 'UTF-8').'">About</a></li>'
		.'<li class="menu-item"><a href="'.htmlspecialchars($base.'service', ENT_QUOTES, 'UTF-8').'">Service</a></li>'
		.'<li class="menu-item"><a href="'.htmlspecialchars($base.'contact', ENT_QUOTES, 'UTF-8').'">Contact</a></li>'
		.'<li class="menu-item"><a href="'.htmlspecialchars($base.'privacy', ENT_QUOTES, 'UTF-8').'">Privacy Policy</a></li>'
		.'<li class="menu-item"><a href="'.htmlspecialchars($base.'terms', ENT_QUOTES, 'UTF-8').'">Terms and Conditions</a></li>';
}

function site_footer_legal_links($current = '')
{
	$base = base_url();
	$current_cls = ' current-menu-item current_page_item';
	$privacy_cls = ($current === 'privacy') ? $current_cls : '';
	$terms_cls = ($current === 'terms') ? $current_cls : '';

	return '<li class="menu-item"><a href="'.htmlspecialchars($base.'contact', ENT_QUOTES, 'UTF-8').'">Support</a></li>'
		.'<li class="menu-item'.$privacy_cls.'"><a href="'.htmlspecialchars($base.'privacy', ENT_QUOTES, 'UTF-8').'"'.($current === 'privacy' ? ' aria-current="page"' : '').'>Privacy Policy</a></li>'
		.'<li class="menu-item'.$terms_cls.'"><a href="'.htmlspecialchars($base.'terms', ENT_QUOTES, 'UTF-8').'"'.($current === 'terms' ? ' aria-current="page"' : '').'>Terms and Conditions</a></li>';
}

function site_footer_service_links()
{
	$base = base_url();
	$html = '';
	foreach (site_services() as $slug => $title) {
		$html .= '<li class="menu-item"><a href="'.htmlspecialchars($base.'service/'.$slug, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</a></li>';
	}
	return $html;
}

function site_service_icons()
{
	$uploads = base_url('assets/website/wp-content/uploads/');
	return array(
		'image-processing'      => $uploads.'2024/06/dataprocessing.png',
		'actionable-insights'   => $uploads.'2024/06/actionable.png',
		'data-stored'           => $uploads.'2024/06/datastored.png',
		'data-processing'       => $uploads.'2024/06/dataprocessing.png',
		'deep-learning'         => $uploads.'2024/06/braindata.png',
		'ai-for-cloud-services' => $uploads.'2024/06/cloud-icon.png',
		'virtual-reality'       => $uploads.'2024/06/virtual.png',
		'machine-learning'      => $uploads.'2024/06/machine.png',
		'robotic-automation'    => $uploads.'2024/07/man.png',
	);
}

function site_all_services_grid_html()
{
	$icons = site_service_icons();
	$summaries = site_service_summaries();
	$html = '<div class="row mb-10">';
	foreach (site_services() as $slug => $title) {
		$url = htmlspecialchars(base_url('service/'.$slug), ENT_QUOTES, 'UTF-8');
		$icon = htmlspecialchars(isset($icons[$slug]) ? $icons[$slug] : '', ENT_QUOTES, 'UTF-8');
		$summary = htmlspecialchars(isset($summaries[$slug]) ? $summaries[$slug] : '', ENT_QUOTES, 'UTF-8');
		$html .= '<div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">'
			.'<a class="site-service-card" href="'.$url.'">'
			.'<div class="tp-iconbox-area"><div class="box-inner"><div class="tp-box-inner-wrapper">'
			.'<div class="icon-area"><img decoding="async" src="'.$icon.'" alt=""></div>'
			.'<div class="text-area"><div class="iconbox-title"><h2 class="title">'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</h2></div>'
			.'<p>'.$summary.'</p></div>'
			.'</div></div></div></a></div>';
	}
	$html .= '</div>';
	return $html;
}

function replace_service_listing_grid($html)
{
	$marker = 'class="tp-icon-box-dynamic"';
	$pos = strpos($html, $marker);
	if ($pos === false) {
		return $html;
	}

	$start = strrpos(substr($html, 0, $pos + 1), '<div');
	if ($start === false) {
		return $html;
	}

	$open_end = strpos($html, '>', $start);
	if ($open_end === false) {
		return $html;
	}

	$cursor = $open_end + 1;
	$depth = 1;
	$len = strlen($html);
	while ($depth > 0 && $cursor < $len) {
		$next_open = strpos($html, '<div', $cursor);
		$next_close = strpos($html, '</div>', $cursor);
		if ($next_close === false) {
			return $html;
		}
		if ($next_open !== false && $next_open < $next_close) {
			$after = substr($html, $next_open + 4, 1);
			if ($after === ' ' || $after === '>' || $after === "\n" || $after === "\r" || $after === "\t") {
				$depth++;
				$cursor = $next_open + 4;
				continue;
			}
			$cursor = $next_open + 4;
			continue;
		}
		$depth--;
		if ($depth === 0) {
			return substr($html, 0, $open_end + 1).site_all_services_grid_html().substr($html, $next_close);
		}
		$cursor = $next_close + 6;
	}

	return $html;
}

function replace_ul_inner_by_id($html, $id, $inner)
{
	$needle = '<ul id="'.$id.'"';
	$start = strpos($html, $needle);
	if ($start === false) {
		return $html;
	}

	$open_end = strpos($html, '>', $start);
	if ($open_end === false) {
		return $html;
	}

	$pos = $open_end + 1;
	$depth = 1;
	$len = strlen($html);

	while ($depth > 0 && $pos < $len) {
		$next_open = strpos($html, '<ul', $pos);
		$next_close = strpos($html, '</ul>', $pos);
		if ($next_close === false) {
			break;
		}
		if ($next_open !== false && $next_open < $next_close) {
			$depth++;
			$pos = $next_open + 3;
		} else {
			$depth--;
			if ($depth === 0) {
				return substr($html, 0, $open_end + 1).$inner.substr($html, $next_close);
			}
			$pos = $next_close + 5;
		}
	}

	return $html;
}

function apply_business_copy($html, $slug = '')
{
	$search = array(
		'<title>intellicon</title>',
		'Unleash the',
		'potential of <span class="theme">AI</span> and machine learning',
		'Machine learning algorithms build a model based on sample data, known as training data, in order to make predictions or decisions...',
		'>Get Started</span>',
		'>Sign In</span>',
		'The world\'s leading AI and machine learning company',
		'There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don\'t look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn\'t anything embarrassing hidden in the middle of text.',
		'AI is the broader concept of machines being able to carry out tasks in a way that would normally require human intelligence.',
		'Machine learning (ML), a fundamental concept of AI research since...',
		'Machine learning (ML), a fundamental concept of AI research&#8230;',
		'Education AI Studies refers to the field of study that combines education and artificial intelligence (AI)',
		'Basic and Premium plans at different levels',
		'The integration of AI and ML is leading to the creation of intelligent systems that can automate tasks, improve decision-making',
		'The integration of AI and ML is leading to the creation of intelligent systems',
		'Revolutionizing industries with cutting-edge AI technology',
		'Artificial intelligence, or AI, is the simulation of human intelligence in machines that are programmed to think and learn. The field of AI research began in the 1950s and has since evolved to encompass a wide range of technologies and applications.',
		'User experience reports on support and services',
		'AI refers to the ability of a computer system to perform tasks that would typically require human intelligence, such as understanding natural language, recognizing objects and patterns in images, and making predictions based on data.',
		'Machine learning is a subset of AI that involves the development of algorithms and statistical models that enable its performance on a task over...',
		'How It’s Work',
		'How It\'s Work',
		'The Most Personalized Object Detection',
		'We Create The Most Realistic AI',
		'Innovative Machine Learning Products',
		'Have any question about us?',
		'Revitalizing Data for a <br>Brighter Future',
		'Get in touch with us',
		'Fill up the form and our team will get back to you within 24 hours',
		'Image Processing for AI',
		'> Data Generated</h2>',
		'> Data Generated</h4>',
		'> Data Processing</h2>',
		'> Data Processing</h4>',
		'> Data Stored</h2>',
		'> Data Stored</h4>',
		'> Actionable Insights</h2>',
		'> Actionable Insights</h4>',
		'> Solutions</h2>',
		'About Us 05',
		'Service &#8211; intellicon',
		'About Us 05 &#8211; intellicon',
		'Contact &#8211; intellicon',
		'Enquiry &#8211; intellicon',
		'<title>Contact',
		'<title>Enquiry',
		'<title>Service',
		'About &#8211; intellicon',
		'AI is the broader concept of machines being able to perform tasks that would normally require human intelligence, such as visual perception, speech recognition, and language translation.',
		'ML, on the other hand, is a specific subfield of AI that is focused on the development of algorithms and statistical models that allow systems to automatically improve their performance with experience',
		'These algorithms and models can be used for a variety of tasks such as prediction, classification, and clustering.',
		'>Technology</span>',
		'Enabling Medical Staff To Prescribe The Right Antibiotics',
		'>Machine</span>',
		'Classifying  listing photos using AI &amp; ML',
		'Davon Lane',
		'Jhon Smith',
		'Jhon Lane',
		'Jhon Willson',
		'David Smith',
		'Micheal Smith',
		'>Read More</span>',
	);

	$replace = array(
		'<title>Turn Every Customer Conversation Into a Sale</title>',
		'Turn Every',
		'Conversation Into a <span class="theme">Sale</span>',
		'AI-powered WhatsApp and social media automation that captures leads, answers customers, follows up automatically, and helps your business sell 24/7. No credit card required.',
		'>Start Free</span>',
		'>Book a Demo</span>',
		'Your Customers Are Already Talking. Are You Responding?',
		'Leads arrive after hours, Instagram comments are ignored, Facebook messages are missed, and teams forget follow-ups. AI handles the repetitive conversations automatically across WhatsApp, Instagram, Facebook, YouTube and your website.',
		'AI understands intent, answers questions, qualifies leads, books appointments, sends products and updates your CRM — then hands off to a human when needed.',
		'Capture the lead, qualify interest, and route it to sales automatically.',
		'Capture the lead, qualify interest, and route it to sales automatically.',
		'Real estate, ecommerce, clinics, education, travel, gyms, restaurants and agencies — one AI layer for every customer conversation.',
		'Starter, Growth and Business plans for every stage',
		'Connect WhatsApp, social channels, CRM, ecommerce and payments into one automation layer.',
		'Connect WhatsApp, social, CRM and ecommerce in one workflow.',
		'We\'re Building the Future of Customer Communication.',
		'Businesses should not need a separate tool for every customer conversation. We bring AI, WhatsApp, social media, CRM, ecommerce, voice and automation into one communication layer.',
		'Mission, vision and a customer-first platform',
		'Mission: help every business reply, qualify and convert on the channels customers already use. Vision: one AI communication layer for WhatsApp, social, voice and CRM.',
		'We build for real operations — not vanity metrics. Product capability first, honest pricing, and human handoff when a conversation needs your team.',
		'How It Works',
		'How It Works',
		'AI that knows your products, FAQs and policies',
		'WhatsApp, Instagram, Facebook and voice in one agent',
		'Automation that captures leads and closes sales',
		'Have a question about automation?',
		'One platform for WhatsApp, <br>Social and AI Sales',
		'Talk to our team',
		'Share your industry, monthly leads and goals. We will get back within 24 hours.',
		'Your AI Sales Agent on WhatsApp',
		'> Customer Finds You</h2>',
		'> Customer Finds You</h4>',
		'> AI Understands</h2>',
		'> AI Understands</h4>',
		'> Automation Takes Over</h2>',
		'> Automation Takes Over</h4>',
		'> Lead Reaches Sales</h2>',
		'> Lead Reaches Sales</h4>',
		'> Convert</h2>',
		'About',
		'Services &#8211; AI Communication Platform',
		'About &#8211; AI Communication Platform',
		'Contact &#8211; AI Communication Platform',
		'Enquiry &#8211; AI Communication Platform',
		'<title>Contact',
		'<title>Enquiry',
		'<title>Service',
		'About &#8211; AI Communication Platform',
		'Starter ₹999, Growth ₹2,999 and Business ₹5,999 per month. WhatsApp, Instagram, Facebook, AI agents, CRM and ecommerce in one platform.',
		'WhatsApp/Meta messaging fees, AI usage, voice provider charges and payment gateway fees may be billed separately where applicable.',
		'Need a custom or enterprise plan? Talk to our team — honest pricing for the channels and automations your business needs.',
		'>WhatsApp</span>',
		'Reply, qualify and convert every WhatsApp lead 24/7',
		'>Social</span>',
		'Turn Instagram and Facebook comments into sales conversations',
		'Real Estate',
		'Ecommerce',
		'Healthcare',
		'Education',
		'Travel',
		'Fitness',
		'>View Service</span>',
	);

	$html = str_replace($search, $replace, $html);

	$more_search = array(
		'Lorem Ipsum is simply dummy text of the printing and typesetting industry.',
		'>Robot</span>',
		'Prescribe correct antibiotics including AI and ML',
		'Unique capabilities in action',
		'Step-by-step user manual for effective and efficient use',
		'Our company has seen significant improvement in efficiency and accuracy since implementing AI and ML technology in our processes. The use of AI and ML has helped us stay ahead of the competition and stay on the cutting...',
		'Our company has seen significant improvement in efficiency and accuracy since implementing AI and ML technology in our processes...',
		'Louce Voiton',
		'AI Expert',
		'Industry Expert',
		'For Multi Use',
		'Personal Use',
		'Full access library',
		'Full acess library',
		'One user',
		'1 analytic report',
		'1 Analytic Reports',
		'5 free optimization',
		'5 Free optimization',
		'Support 24/7',
		'Hotline supports 24/7',
		'<h4 class="tp-pricing-table-title title">Premium</h4>',
		'<h4 class="tp-pricing-table-title title">Basic</h4>',
		'<h4 class="tp-pricing-table-title title">Standard</h4>',
		'<h4 class="tp-pricing-table-title">Free</h4>',
		'<h4 class="tp-pricing-table-title">Pro</h4>',
		'<h4 class="tp-pricing-table-title">Premium</h4>',
		'/15 Days',
		'/365 Days',
		'                                    25.00                                    </h3>',
		'                                    00.00                                    </h3>',
		'                                    5.00                                    </h3>',
		'&#036;</span>00.00',
		'&#036;</span>60.00',
		'&#036;</span>90.00',
		'&#036;</span>199',
		'&#036;</span>399',
		'class="dollar">&#036;</span>',
		'class="dollar tp-el-currency-sign">&#036;</span>',
		'What is the difference between AI and ML?',
		'What are some common applications of AI and ML?',
		'How can I get started with AI and ML?',
		'What skills do I need to work in AI and ML?',
		'What are the benefits of using AI and ML in business?',
		'Are there any limitations to AI and ML?',
		'Frequently Asked Questions &amp; Answers',
		'tp-portfolio-category/technology/index.html',
		'tp-portfolio-category/machine/index.html',
		'tp-portfolio-category/robot/index.html',
		'<a class="slider-btn capa__more tp-el-btn"  href="#">',
		'<a class="cmn--btn border__btn" href="#">',
		'>See All Case Studies</span>',
		'AI (Artificial Intelligence) and ML (Machine Learning) are closely related fields that are focused on the development of computer systems...',
	);

	$more_replace = array(
		'AI replies, qualifies leads and hands off to your team on WhatsApp, Instagram and Facebook.',
		'>Voice</span>',
		'AI voice that qualifies callers and books follow-ups',
		'How businesses use the platform',
		'From first message to a booked sale',
		'WhatsApp AI now replies after hours, qualifies interest, and books follow-ups so the team only speaks to ready buyers.',
		'WhatsApp AI now replies after hours, qualifies interest, and books follow-ups so the team only speaks to ready buyers.',
		'WhatsApp automation',
		'Clinic operations',
		'Store operations',
		'Multi-channel teams',
		'Getting started',
		'WhatsApp AI replies',
		'WhatsApp AI replies',
		'Lead qualification',
		'CRM follow-ups',
		'CRM follow-ups',
		'Social inbox automation',
		'Social inbox automation',
		'Human handoff 24/7',
		'Human handoff 24/7',
		'<h4 class="tp-pricing-table-title title">Business</h4>',
		'<h4 class="tp-pricing-table-title title">Starter</h4>',
		'<h4 class="tp-pricing-table-title title">Growth</h4>',
		'<h4 class="tp-pricing-table-title">Starter</h4>',
		'<h4 class="tp-pricing-table-title">Growth</h4>',
		'<h4 class="tp-pricing-table-title">Business</h4>',
		'/month',
		'/month',
		'                                    5,999                                    </h3>',
		'                                    999                                    </h3>',
		'                                    2,999                                    </h3>',
		'₹</span>999',
		'₹</span>2,999',
		'₹</span>5,999',
		'₹</span>2,999',
		'₹</span>5,999',
		'class="dollar">₹</span>',
		'class="dollar tp-el-currency-sign">₹</span>',
		'Which channels does the platform cover?',
		'Can it book appointments and send products?',
		'How do I get started?',
		'Do I need developers to use this?',
		'What costs sit outside the monthly plan?',
		'When does a human take over?',
		'Frequently Asked Questions &amp; Answers',
		base_url('service/image-processing'),
		base_url('service/actionable-insights'),
		base_url('service/ai-for-cloud-services'),
		'<a class="slider-btn capa__more tp-el-btn"  href="'.htmlspecialchars(base_url('service'), ENT_QUOTES, 'UTF-8').'">',
		'<a class="cmn--btn border__btn" href="'.htmlspecialchars(base_url('contact'), ENT_QUOTES, 'UTF-8').'">',
		'>See all services</span>',
		'Saree shops, clinics, stores and social sellers — tap a story to see the matching service.',
	);

	$html = str_replace($more_search, $more_replace, $html);

	$faq_answers = array(
		'WhatsApp, Instagram, Facebook, YouTube comments, website chat and voice — one AI layer that captures leads, answers FAQs and updates your CRM.',
		'Yes. The AI can qualify, share catalogues, collect payments and book slots, then update your CRM.',
		'Share your industry and monthly lead volume on Contact. We connect WhatsApp and go live. No credit card is required to start.',
		'No. Use the no-code Automation Builder. APIs and webhooks are available if your team wants custom integrations.',
		'WhatsApp/Meta messaging, AI usage, voice minutes and payment gateway fees may be billed separately where applicable.',
		'The AI handles repetitive questions and qualification. Anything complex, angry or high-value is handed to your team with full chat context.',
	);
	$old_faq = 'Learn the basics: Acquire a basic understanding of AI and ML concepts and technologies by reading books, taking online courses, or attending workshops.';
	foreach ($faq_answers as $answer) {
		$pos = strpos($html, $old_faq);
		if ($pos === false) {
			break;
		}
		$html = substr_replace($html, $answer, $pos, strlen($old_faq));
	}

	foreach (site_services() as $key => $title) {
		$old = array(
			'image-processing'      => 'Image Processing',
			'actionable-insights'   => 'Actionable Insights',
			'data-stored'           => 'Data Stored',
			'data-processing'       => 'Data Processing',
			'deep-learning'         => 'Deep Learning',
			'ai-for-cloud-services' => 'Ai For Cloud Services',
			'virtual-reality'       => 'Virtual Reality',
			'machine-learning'      => 'Machine Learning',
			'robotic-automation'    => 'Robotic Automation',
		);
		if (isset($old[$key])) {
			$html = str_replace($old[$key], $title, $html);
		}
	}

	if ($slug !== '' && isset(site_services()[$slug])) {
		$title = site_services()[$slug];
		$summary = site_service_summaries()[$slug];
		$html = str_replace(
			array('Your AI Sales Agent on WhatsApp', $title.' for AI'),
			array($title, $title),
			$html
		);
		$html = preg_replace(
			'#(<h1 class="page-title">\s*)'.preg_quote($title, '#').'(\s*</h1>)#',
			'$1'.$title.'$2',
			$html
		);
		$html = str_replace(
			'<title>'.$title.' &#8211; intellicon</title>',
			'<title>'.$title.' &#8211; AI Communication Platform</title>',
			$html
		);
		$html = str_replace(
			'Capture the lead, qualify interest, and route it to sales automatically.',
			$summary,
			$html
		);
	}

	return $html;
}

function remove_matching_div($html, $start)
{
	if ($start === false || substr($html, $start, 4) !== '<div') {
		return $html;
	}

	$open_end = strpos($html, '>', $start);
	if ($open_end === false) {
		return $html;
	}

	$pos = $open_end + 1;
	$depth = 1;
	$len = strlen($html);

	while ($depth > 0 && $pos < $len) {
		$next_open = strpos($html, '<div', $pos);
		$next_close = strpos($html, '</div>', $pos);
		if ($next_close === false) {
			break;
		}
		if ($next_open !== false && $next_open < $next_close) {
			$after = substr($html, $next_open + 4, 1);
			if ($after === ' ' || $after === '>' || $after === "\n" || $after === "\r" || $after === "\t") {
				$depth++;
				$pos = $next_open + 4;
				continue;
			}
			$pos = $next_open + 4;
			continue;
		}
		$depth--;
		if ($depth === 0) {
			return substr($html, 0, $start).substr($html, $next_close + 6);
		}
		$pos = $next_close + 6;
	}

	return $html;
}

function remove_divs_by_marker($html, $marker)
{
	$guard = 0;
	while ($guard < 20) {
		$pos = strpos($html, $marker);
		if ($pos === false) {
			break;
		}
		$start = strrpos(substr($html, 0, $pos + 1), '<div');
		if ($start === false) {
			break;
		}
		$next = remove_matching_div($html, $start);
		if ($next === $html) {
			break;
		}
		$html = $next;
		$guard++;
	}
	return $html;
}

function site_sidebar_services_html()
{
	$base = base_url();
	$html = '<div class="recent-widget widget"><h4 class="widget-title">Our Services</h4><div class="recent-post-widget clearfix">';
	foreach (site_services() as $slug => $title) {
		$html .= '<div class="show-featured clearfix"><div class="post-item"><div class="post-desc"><a href="'
			.htmlspecialchars($base.'service/'.$slug, ENT_QUOTES, 'UTF-8').'">'
			.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</a></div></div></div>';
	}
	$html .= '</div></div>';
	return $html;
}

function replace_recent_posts_widget($html)
{
	$marker = 'class="recent-widget widget"';
	$pos = strpos($html, $marker);
	if ($pos === false) {
		return $html;
	}
	$start = strrpos(substr($html, 0, $pos + 1), '<div');
	if ($start === false) {
		return $html;
	}

	$open_end = strpos($html, '>', $start);
	if ($open_end === false) {
		return $html;
	}

	$cursor = $open_end + 1;
	$depth = 1;
	$len = strlen($html);
	while ($depth > 0 && $cursor < $len) {
		$next_open = strpos($html, '<div', $cursor);
		$next_close = strpos($html, '</div>', $cursor);
		if ($next_close === false) {
			return $html;
		}
		if ($next_open !== false && $next_open < $next_close) {
			$after = substr($html, $next_open + 4, 1);
			if ($after === ' ' || $after === '>' || $after === "\n" || $after === "\r" || $after === "\t") {
				$depth++;
				$cursor = $next_open + 4;
				continue;
			}
			$cursor = $next_open + 4;
			continue;
		}
		$depth--;
		if ($depth === 0) {
			return substr($html, 0, $start).site_sidebar_services_html().substr($html, $next_close + 6);
		}
		$cursor = $next_close + 6;
	}

	return $html;
}

function strip_search_and_category($html)
{
	$html = remove_divs_by_marker($html, 'tps-search-popup');
	$html = remove_divs_by_marker($html, 'hfe-search-layout-icon');
	$html = remove_divs_by_marker($html, 'widget_tptheme_search_widget');
	$html = remove_divs_by_marker($html, 'themephi-pcat');
	$html = replace_recent_posts_widget($html);

	$css = '<style id="site-hide-unused">.sticky_form.tps-search-popup,.hfe-search-layout-icon,.widget_tptheme_search_widget,.widget.themephi-pcat{display:none!important}a.site-service-card{display:block;color:inherit;text-decoration:none}a.site-service-card .title,a.site-service-card p{color:inherit}a.site-service-card .tp-iconbox-area{height:100%}.site-brand-text{display:inline-block;font-weight:800;font-size:22px;letter-spacing:-.04em;color:#fff;line-height:1;white-space:nowrap}.site-brand-text span{color:#A58EFF}</style>';
	$html = preg_replace('#</head>#i', $css.'</head>', $html, 1);

	return $html;
}

function rewrite_template_html($html, $current = 'home')
{
	$html = strip_search_and_category($html);

	$base = base_url();
	$assets = base_url('assets/website/');

	// Drop WP speculation rules (paths break after rewrite)
	$html = preg_replace('#<script type="speculationrules">.*?</script>#is', '', $html);

	// Drop emoji loader pair only (never match arbitrary <script>…_wpemojiSettings — that ate the whole page)
	$html = preg_replace(
		'#<script id="wp-emoji-settings"[^>]*>.*?</script>\s*<script type="module">.*?</script>#is',
		'',
		$html
	);

	// Absolute mirrored host → local assets
	$html = preg_replace('#https?://softivuslab\.com/wp/intellicon/(wp-content/)#i', $assets.'$1', $html);
	$html = preg_replace('#https?://softivuslab\.com/wp/intellicon/(wp-includes/)#i', $assets.'$1', $html);

	// Absolute-path leftovers from the mirror (/wp/intellicon/wp-content/...)
	$html = preg_replace('#(?:https?://[^"\'\s]+)?/wp/intellicon/(wp-content/)#i', $assets.'$1', $html);
	$html = preg_replace('#(?:https?://[^"\'\s]+)?/wp/intellicon/(wp-includes/)#i', $assets.'$1', $html);

	// Relative wp-content / wp-includes (do not touch already-rewritten assets/website/...)
	$html = preg_replace('#(?<!assets/website/)(?:\.\./)*wp-content/#', $assets.'wp-content/', $html);
	$html = preg_replace('#(?<!assets/website/)(?:\.\./)*wp-includes/#', $assets.'wp-includes/', $html);

	// Collapse accidental double prefixes from older rewrites / srcset
	$html = str_replace($assets.$assets, $assets, $html);
	$html = preg_replace('#'.preg_quote($assets, '#').'https?://[^/]+/assets/website/#i', $assets, $html);

	$html = preg_replace('#https?://softivuslab\.com/wp/intellicon/?#i', $base, $html);
	$html = preg_replace('#"/wp/intellicon/#i', '"'.$base, $html);
	$html = preg_replace("#'/wp/intellicon/#i", "'".$base, $html);

	$html = preg_replace('#(?:\.\./)*services/([a-z0-9\-]+)/index\.html#i', $base.'service/$1', $html);
	$html = preg_replace('#(?:\.\./)*service/index\.html#i', $base.'service', $html);
	$html = preg_replace('#(?:\.\./)*about-us-05/index\.html#i', $base.'about', $html);
	$html = preg_replace('#(?:\.\./)*about-style-02/index\.html#i', $base.'about', $html);
	$html = preg_replace('#(?:\.\./)*about-us-0[34]/index\.html#i', $base.'about', $html);
	$html = preg_replace('#(?:\.\./)*about/index\.html#i', $base.'about', $html);
	$html = preg_replace('#(?:\.\./)*contact/index\.html#i', $base.'contact', $html);
	$html = preg_replace('#(?:\.\./)*case-studies-0[1-5]/index\.html#i', $base.'service', $html);

	$html = replace_ul_inner_by_id($html, 'primary-menu-single1', site_menu_items($current));
	$html = replace_ul_inner_by_id($html, 'menu-main-menu', site_menu_items($current));
	$html = replace_ul_inner_by_id($html, 'menu-quick-links', site_footer_quick_links());
	$html = replace_ul_inner_by_id($html, 'menu-services', site_footer_service_links());
	$html = replace_ul_inner_by_id($html, 'menu-footer-menu', site_footer_legal_links($current));

	$html = preg_replace('#href="index\.html"#i', 'href="'.$base.'"', $html);
	$html = preg_replace("#href='index\.html'#i", "href='".$base."'", $html);
	$html = preg_replace('#href="(?:\.\./)*wp-login\.html"#i', 'href="'.$base.'contact"', $html);

	$legal_bar = ' &nbsp;|&nbsp; <a href="'.htmlspecialchars($base.'privacy', ENT_QUOTES, 'UTF-8').'">Privacy Policy</a> &nbsp;|&nbsp; <a href="'.htmlspecialchars($base.'terms', ENT_QUOTES, 'UTF-8').'">Terms and Conditions</a>';
	$html = str_replace(
		'Designed By <a class="theme" href="https://themeforest.net/user/pixelaxis">Pixelaxis</a>',
		'Designed By <a class="theme" href="https://themeforest.net/user/pixelaxis">Pixelaxis</a>'.$legal_bar,
		$html
	);

	if ($current === 'service') {
		$html = replace_service_listing_grid($html);
	}

	if ($current === 'home') {
		$html = replace_hero_thumb($html);
		$html = inject_business_story($html);
		$html = replace_key_features_journey($html);
		$html = replace_case_studies_slider($html);
	}

	$html = preg_replace('#https?://(?:www\.)?youtube\.com/watch\?v=[^"\']+#i', '#site-how-it-works', $html);
	$html = apply_site_brand($html);
	$html = inject_how_it_works($html);

	return $html;
}

function site_brand_name()
{
	return 'TalkAIPilot';
}

function apply_site_brand($html)
{
	$brand = site_brand_name();

	$html = str_replace(
		array(
			'>Intellicon</a>',
			'>intellicon</span>',
			'title="Go to intellicon."',
			'title="intellicon &raquo; Feed"',
			'title="intellicon &raquo; Comments Feed"',
			' &#8211; intellicon',
			' &#8211; AI Communication Platform',
		),
		array(
			'>'.$brand.'</a>',
			'>'.$brand.'</span>',
			'title="Go to '.$brand.'."',
			'title="'.$brand.' &raquo; Feed"',
			'title="'.$brand.' &raquo; Comments Feed"',
			' &#8211; '.$brand,
			' &#8211; '.$brand,
		),
		$html
	);

	$html = preg_replace(
		'#<img class="hfe-site-logo-img[^"]*"\s+src="[^"]+"\s+alt="[^"]*"/>#',
		'<span class="site-brand-text">Talk<span>AI</span>Pilot</span>',
		$html
	);

	return $html;
}

function site_how_it_works_html()
{
	$enquiry = htmlspecialchars(base_url('contact'), ENT_QUOTES, 'UTF-8');
	$css = htmlspecialchars(base_url('assets/website/site-how-it-works.css'), ENT_QUOTES, 'UTF-8');
	$js = htmlspecialchars(base_url('assets/website/site-how-it-works.js'), ENT_QUOTES, 'UTF-8');

	return '<link rel="stylesheet" href="'.$css.'?v=5">'
		.'<div id="site-how-it-works" class="site-hiw" aria-hidden="true">'
		.'<div class="site-hiw-overlay"></div>'
		.'<div class="site-hiw-panel" role="dialog" aria-labelledby="site-hiw-title">'
		.'<button type="button" class="site-hiw-close" aria-label="Close">&times;</button>'
		.'<div class="site-hiw-top"><div class="site-hiw-brand"><span class="site-hiw-kicker">TalkAIPilot intro</span>'
		.'<h3 id="site-hiw-title">See how a saree enquiry becomes a paid order</h3></div></div>'
		.'<div class="site-hiw-progress"><span></span></div>'
		.'<div class="site-hiw-body">'
		.'<div class="site-hiw-grid">'
		.'<div class="site-hiw-phone-wrap"><div class="site-hiw-phone">'
		.'<div class="site-hiw-notch"></div>'
		.'<div class="site-hiw-status"><span>9:41</span><span>5G</span></div>'
		.'<div class="site-hiw-channels"><span class="site-hiw-chip" data-chip="whatsapp">WhatsApp</span><span class="site-hiw-chip" data-chip="instagram">Instagram</span><span class="site-hiw-chip" data-chip="facebook">Facebook</span></div>'
		.'<div class="site-hiw-head"><div class="site-hiw-avatar">AI</div><div><strong>Silk House</strong><span><i class="site-hiw-live"></i>Online · replies in seconds</span></div></div>'
		.'<div class="site-hiw-chat">'
		.'<div class="site-hiw-bubble site-hiw-in site-hiw-b1">Hi, do you have a Kanchipuram silk saree in maroon for a wedding this weekend?<span class="site-hiw-meta">Lead · after hours</span></div>'
		.'<div class="site-hiw-typing"><i></i><i></i><i></i></div>'
		.'<div class="site-hiw-bubble site-hiw-out site-hiw-b2"><span data-type="Yes — Kanchipuram silk, maroon with gold zari, 6 yards. Price ₹12,499, in stock. Shall I send this one?"></span><span class="site-hiw-meta">Product details</span></div>'
		.'<div class="site-hiw-card site-hiw-product"><b>Kanchipuram Silk Saree</b><small>Maroon &amp; gold zari · 6 yards · ₹12,499 · In stock</small></div>'
		.'<div class="site-hiw-bubble site-hiw-in site-hiw-b3">Yes, I want this. Please send the payment link.</div>'
		.'<div class="site-hiw-card site-hiw-pay"><b>Pay ₹12,499</b><small>UPI / Card / Net banking · tap to pay</small></div>'
		.'<div class="site-hiw-toast site-hiw-paid">Payment confirmed · ₹12,499 received</div>'
		.'<div class="site-hiw-bubble site-hiw-out site-hiw-b4">Payment received. Your invoice is being sent now.</div>'
		.'<div class="site-hiw-card site-hiw-invoice"><b>Invoice sent</b><small>Order #SA-1842 · Silk House · WhatsApp PDF</small></div>'
		.'<div class="site-hiw-tag">Asked · paid · invoice sent</div>'
		.'</div></div></div>'
		.'<div class="site-hiw-steps">'
		.'<div class="site-hiw-step"><span class="site-hiw-num">1</span><div><h4>Customer asks</h4><p>A saree shop lead messages on WhatsApp after hours — colour, occasion and stock.</p></div></div>'
		.'<div class="site-hiw-step"><span class="site-hiw-num">2</span><div><h4>Product details</h4><p>AI replies with fabric, zari, length, price and stock, then asks if they want this saree.</p></div></div>'
		.'<div class="site-hiw-step"><span class="site-hiw-num">3</span><div><h4>Payment link</h4><p>Customer confirms the product. The agent sends a UPI / card payment link.</p></div></div>'
		.'<div class="site-hiw-step"><span class="site-hiw-num">4</span><div><h4>Payment confirmed</h4><p>Once the amount is paid, the order is marked paid before anything else is sent.</p></div></div>'
		.'<div class="site-hiw-step"><span class="site-hiw-num">5</span><div><h4>Invoice sent</h4><p>After payment confirm, the invoice PDF is sent on WhatsApp and the sale is closed.</p></div></div>'
		.'</div></div>'
		.'<div class="site-hiw-actions">'
		.'<button type="button" class="site-hiw-replay">Replay</button>'
		.'<a class="site-hiw-cta" href="'.$enquiry.'">Start Free</a>'
		.'</div></div></div></div>'
		.'<script src="'.$js.'?v=4"></script>';
}

function site_business_story_html()
{
	$enquiry = htmlspecialchars(base_url('contact'), ENT_QUOTES, 'UTF-8');
	$css = htmlspecialchars(base_url('assets/website/site-business-story.css'), ENT_QUOTES, 'UTF-8');
	$js = htmlspecialchars(base_url('assets/website/site-business-story.js'), ENT_QUOTES, 'UTF-8');

	return '<link rel="stylesheet" href="'.$css.'?v=8">'
		.'<section class="site-story" id="how-talkaipilot-works">'
		.'<div class="site-story-inner">'
		.'<span class="site-story-kicker">The conversation</span>'
		.'<h2>Chat. Product. Pay. Invoice.</h2>'
		.'<p class="site-story-lead">One WhatsApp thread. The AI stays with the customer from the first message until the invoice is sent.</p>'
		.'<ol class="site-story-flow">'
		.'<li class="site-story-card is-on"><span class="site-story-num">1</span><div class="site-story-stage ss-chat"><div class="ss-b in">Maroon Kanchipuram, this weekend?</div><div class="ss-b out">Yes — in stock. Sending it now.</div></div><h3>Chats</h3><p>The customer messages after hours. The AI picks it up in seconds.</p></li>'
		.'<li class="site-story-card is-on"><span class="site-story-num">2</span><div class="site-story-stage"><div class="ss-prod"><div class="ss-swatch"><i></i><i></i><i></i></div><b>Kanchipuram Silk</b><small>Maroon &amp; gold · 6 yards · ₹12,499</small></div></div><h3>Sees the product</h3><p>Colour, fabric, length, price and stock — then a clear yes/no.</p></li>'
		.'<li class="site-story-card is-on"><span class="site-story-num">3</span><div class="site-story-stage"><div class="ss-pay">Pay ₹12,499<span>UPI / Card / Net banking</span></div></div><h3>Pays</h3><p>A payment link in the same chat. Marked paid only after the money lands.</p></li>'
		.'<li class="site-story-card is-on"><span class="site-story-num">4</span><div class="site-story-stage"><div class="ss-inv"><b>Invoice · SA-1842</b><small>WhatsApp PDF · Silk House</small><div class="ss-line"></div><div class="ss-line"></div></div></div><h3>Gets the invoice</h3><p>Invoice goes out on WhatsApp. Your team only packs a paid order.</p></li>'
		.'</ol>'
		.'<div class="site-story-who">'
		.'<article class="is-on"><h4>Saree &amp; boutiques</h4><p>Catalogue, colour, pay link, invoice.</p></article>'
		.'<article class="is-on"><h4>Clinics &amp; services</h4><p>FAQs, slot booking, reminders.</p></article>'
		.'<article class="is-on"><h4>Stores &amp; D2C</h4><p>Stock, cart, payment, CRM.</p></article>'
		.'<article class="is-on"><h4>Any WhatsApp shop</h4><p>If they message you, the AI can sell.</p></article>'
		.'</div>'
		.'<div class="site-story-cta"><a href="'.$enquiry.'">Start Free</a></div>'
		.'</div></section>'
		.'<script src="'.$js.'?v=5"></script>';
}

function replace_div_by_marker($html, $marker, $replacement)
{
	$pos = strpos($html, $marker);
	if ($pos === false) {
		return $html;
	}
	$start = strrpos(substr($html, 0, $pos + 1), '<div');
	if ($start === false || substr($html, $start, 4) !== '<div') {
		return $html;
	}

	$open_end = strpos($html, '>', $start);
	if ($open_end === false) {
		return $html;
	}

	$cursor = $open_end + 1;
	$depth = 1;
	$len = strlen($html);
	while ($depth > 0 && $cursor < $len) {
		$next_open = strpos($html, '<div', $cursor);
		$next_close = strpos($html, '</div>', $cursor);
		if ($next_close === false) {
			return $html;
		}
		if ($next_open !== false && $next_open < $next_close) {
			$after = substr($html, $next_open + 4, 1);
			if ($after === ' ' || $after === '>' || $after === "\n" || $after === "\r" || $after === "\t") {
				$depth++;
				$cursor = $next_open + 4;
				continue;
			}
			$cursor = $next_open + 4;
			continue;
		}
		$depth--;
		if ($depth === 0) {
			return substr($html, 0, $start).$replacement.substr($html, $next_close + 6);
		}
		$cursor = $next_close + 6;
	}

	return $html;
}

function site_sale_path_steps()
{
	return array(
		array('1', 'Customer chats', 'A lead messages on WhatsApp after hours — colour, size, price or stock.'),
		array('2', 'AI shows the product', 'The assistant replies with details, photo, price and whether it is in stock.'),
		array('3', 'Customer says yes', 'They confirm the item in the same chat. No app switch, no lost thread.'),
		array('4', 'Pays in chat', 'A UPI / card link is sent. The order is marked paid only after money is received.'),
		array('5', 'Invoice sent', 'The invoice PDF goes out on WhatsApp. The sale is closed.'),
		array('6', 'Your team ships', 'Staff get a paid order with the full chat. AI keeps covering the next lead.'),
	);
}

function site_sale_path_html()
{
	$enquiry = htmlspecialchars(base_url('contact'), ENT_QUOTES, 'UTF-8');
	$robot = htmlspecialchars(base_url('assets/website/wp-content/uploads/2024/06/feature.png'), ENT_QUOTES, 'UTF-8');
	$steps = site_sale_path_steps();
	$left = array_slice($steps, 0, 3);
	$right = array_slice($steps, 3, 3);

	$card = function ($step, $side) {
		return '<article class="site-path-card site-path-'.$side.'">'
			.'<span class="site-path-num">'.$step[0].'</span>'
			.'<div class="site-path-body"><h3>'.$step[1].'</h3><p>'.$step[2].'</p></div>'
			.'</article>';
	};

	$css = htmlspecialchars(base_url('assets/website/site-business-story.css'), ENT_QUOTES, 'UTF-8');
	$html = '<link rel="stylesheet" href="'.$css.'?v=8">'
		.'<section class="site-path" id="site-sale-path">'
		.'<div class="site-path-inner">'
		.'<span class="site-path-kicker">How a sale happens</span>'
		.'<h2>One AI assistant. From first chat to paid invoice.</h2>'
		.'<p class="site-path-lead">Follow the path: the customer chats, sees the product, pays, then gets the invoice. Each stop is one step in the same WhatsApp conversation.</p>'
		.'<div class="site-path-board">'
		.'<div class="site-path-col">';
	foreach ($left as $step) {
		$html .= $card($step, 'left');
	}
	$html .= '</div>'
		.'<div class="site-path-hero"><img src="'.$robot.'" alt="TalkAIPilot assistant" width="400" height="584">'
		.'<a class="site-path-cta" href="'.$enquiry.'">Start Free</a></div>'
		.'<div class="site-path-col">';
	foreach ($right as $step) {
		$html .= $card($step, 'right');
	}
	$html .= '</div></div>'
		.'<div class="site-path-mobile-cta"><a class="site-path-cta" href="'.$enquiry.'">Start Free</a></div>'
		.'</div></section>';

	return $html;
}

function site_hero_demo_html()
{
	$css = htmlspecialchars(base_url('assets/website/site-hero-demo.css'), ENT_QUOTES, 'UTF-8');
	return '<link rel="stylesheet" href="'.$css.'?v=4">'
		.'<div class="site-hero-demo" aria-hidden="true">'
		.'<div class="site-hero-phone">'
		.'<div class="site-hero-notch"></div>'
		.'<div class="site-hero-status"><span>9:41</span><span>5G</span></div>'
		.'<div class="site-hero-head"><div class="site-hero-ava">AI</div><div><strong>Silk House</strong><small><i class="site-hero-live"></i>Online · replies in seconds</small></div></div>'
		.'<div class="site-hero-chat">'
		.'<div class="site-hero-b site-hero-in site-hero-b1">Do you have a maroon Kanchipuram saree for this weekend?<span class="site-hero-meta">Lead · after hours</span></div>'
		.'<div class="site-hero-type"><i></i><i></i><i></i></div>'
		.'<div class="site-hero-b site-hero-out site-hero-b2">Yes — maroon &amp; gold zari, 6 yards, ₹12,499, in stock. Sending it now.</div>'
		.'<div class="site-hero-card site-hero-prod"><div class="site-hero-sw"><i></i><i></i><i></i></div><b>Kanchipuram Silk Saree</b><small>Maroon &amp; gold · In stock · ₹12,499</small></div>'
		.'<div class="site-hero-card site-hero-pay"><b>Pay ₹12,499</b><small>UPI / Card / Net banking</small></div>'
		.'<div class="site-hero-toast">Payment confirmed · ₹12,499 received</div>'
		.'<div class="site-hero-card site-hero-inv"><b>Invoice sent</b><small>Order #SA-1842 · WhatsApp PDF</small></div>'
		.'<div class="site-hero-tag">Asked · paid · invoice sent</div>'
		.'</div></div>'
		.'<ol class="site-hero-mobile">'
		.'<li><span>1</span><b>Chats</b><small>Lead messages on WhatsApp</small></li>'
		.'<li><span>2</span><b>Product</b><small>AI sends price and stock</small></li>'
		.'<li><span>3</span><b>Pays</b><small>UPI link in the same chat</small></li>'
		.'<li><span>4</span><b>Invoice</b><small>PDF after payment</small></li>'
		.'</ol></div>';
}

function replace_hero_thumb($html)
{
	return replace_div_by_marker($html, 'class="banner__thumb"', '<div class="banner__thumb">'.site_hero_demo_html().'</div>');
}

function replace_key_features_journey($html)
{
	return replace_div_by_marker($html, 'class="elementor-element elementor-element-d37bc6d', site_sale_path_html());
}

function site_use_cases()
{
	$img = base_url('assets/website/wp-content/uploads/2024/07/');
	return array(
		array(
			'img'   => $img.'capbi1.jpg',
			'tag'   => 'WhatsApp',
			'title' => 'Saree & boutique shops',
			'text'  => 'Colour, stock, pay link and invoice in one WhatsApp thread.',
			'slug'  => 'image-processing',
		),
		array(
			'img'   => $img.'capabi2.jpg',
			'tag'   => 'Clinics',
			'title' => 'Clinics & service bookings',
			'text'  => 'FAQs, slot booking and reminders so the front desk is not the bottleneck.',
			'slug'  => 'data-stored',
		),
		array(
			'img'   => $img.'capabi3.jpg',
			'tag'   => 'Stores',
			'title' => 'Stores & D2C brands',
			'text'  => 'Catalogue, cart and checkout inside chat. Staff only pack paid orders.',
			'slug'  => 'deep-learning',
		),
		array(
			'img'   => $img.'capbi1.jpg',
			'tag'   => 'Social',
			'title' => 'Instagram & Facebook sellers',
			'text'  => 'Comments and DMs become a sales conversation, then a CRM lead.',
			'slug'  => 'actionable-insights',
		),
	);
}

function site_use_cases_html()
{
	$css = htmlspecialchars(base_url('assets/website/site-business-story.css'), ENT_QUOTES, 'UTF-8');
	$html = '<link rel="stylesheet" href="'.$css.'?v=8">'
		.'<div class="site-cases" id="site-use-cases">';
	foreach (site_use_cases() as $case) {
		$url = htmlspecialchars(base_url('service/'.$case['slug']), ENT_QUOTES, 'UTF-8');
		$html .= '<a class="site-case" href="'.$url.'">'
			.'<span class="site-case-media"><img src="'.htmlspecialchars($case['img'], ENT_QUOTES, 'UTF-8').'" alt="'.htmlspecialchars($case['title'], ENT_QUOTES, 'UTF-8').'" width="640" height="400"></span>'
			.'<span class="site-case-tag">'.htmlspecialchars($case['tag'], ENT_QUOTES, 'UTF-8').'</span>'
			.'<h3>'.htmlspecialchars($case['title'], ENT_QUOTES, 'UTF-8').'</h3>'
			.'<p>'.htmlspecialchars($case['text'], ENT_QUOTES, 'UTF-8').'</p>'
			.'<span class="site-case-more">Read more</span>'
			.'</a>';
	}
	$html .= '</div>';
	return $html;
}

function replace_case_studies_slider($html)
{
	return replace_div_by_marker($html, 'class="elementor-element elementor-element-138c83c', site_use_cases_html());
}

function inject_business_story($html)
{
	$block = site_business_story_html();
	$marker = 'class="elementor-element elementor-element-8c7c666';
	$pos = strpos($html, $marker);
	if ($pos !== false) {
		$start = strrpos(substr($html, 0, $pos + 1), '<div');
		if ($start !== false) {
			return substr($html, 0, $start).$block.substr($html, $start);
		}
	}
	return preg_replace('#</main>#i', $block.'</main>', $html, 1);
}

function inject_how_it_works($html)
{
	$block = site_how_it_works_html();
	if (stripos($html, '</body>') !== false) {
		return preg_replace('#</body>#i', $block.'</body>', $html, 1);
	}
	return $html.$block;
}

function apply_enquiry_copy($html)
{
	return apply_page_chrome($html, 'Enquiry', site_contact_form_html('enquiry'));
}

function apply_page_chrome($html, $title, $content)
{
	$safe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
	$html = preg_replace('#<title>.*?</title>#', '<title>'.$safe.' &#8211; TalkAIPilot</title>', $html, 1);
	$html = preg_replace('#(<h1 class="page-title">\s*)[^<]+#', '$1'.$safe, $html, 1);
	$html = preg_replace('#class="post post-page current-item">[^<]*</span>#', 'class="post post-page current-item">'.$safe.'</span>', $html, 1);

	$css = '<link rel="stylesheet" href="'.htmlspecialchars(base_url('assets/website/site-pages.css'), ENT_QUOTES, 'UTF-8').'?v=2">';
	$html = preg_replace('#</head>#i', $css.'</head>', $html, 1);

	$start = strpos($html, '<div id="content">');
	$end = strpos($html, '</div><!-- .content -->');
	if ($start !== false && $end !== false) {
		$html = substr($html, 0, $start).'<div id="content">'.$content.substr($html, $end);
	}

	return $html;
}

function site_about_content_html()
{
	$enquiry = htmlspecialchars(base_url('contact'), ENT_QUOTES, 'UTF-8');
	$service = htmlspecialchars(base_url('service'), ENT_QUOTES, 'UTF-8');
	$img = htmlspecialchars(base_url('assets/website/wp-content/uploads/2024/06/about1-1.png'), ENT_QUOTES, 'UTF-8');

	return '<section class="site-page"><div class="site-page-wrap">'
		.'<div class="site-page-hero">'
		.'<div class="site-page-visual"><img src="'.$img.'" width="420" height="517" alt="TalkAIPilot"></div>'
		.'<div class="site-page-copy">'
		.'<span class="site-page-kicker">About TalkAIPilot</span>'
		.'<h2>Your customers are already talking. We help you sell.</h2>'
		.'<p>TalkAIPilot is the AI assistant for shops and service businesses. It replies on WhatsApp, Instagram, Facebook, YouTube and the website — then turns those chats into paid orders.</p>'
		.'<p>Leads arrive after hours. Comments sit unread. Follow-ups get forgotten. The agent answers product questions, qualifies interest, sends a payment link, and hands a paid order to your team.</p>'
		.'<ul class="site-page-checks">'
		.'<li>24/7 WhatsApp replies</li>'
		.'<li>Product, price and stock</li>'
		.'<li>Pay link in the same chat</li>'
		.'<li>Invoice after payment</li>'
		.'</ul>'
		.'<div class="site-page-actions">'
		.'<a class="site-page-btn" href="'.$enquiry.'">Start Free</a>'
		.'<a class="site-page-btn site-page-btn-ghost" href="'.$service.'">See services</a>'
		.'</div></div></div>'
		.'<div class="site-page-grid">'
		.'<article class="site-page-card"><h3>Saree &amp; boutiques</h3><p>Catalogue, colour, size, pay link and invoice on WhatsApp.</p></article>'
		.'<article class="site-page-card"><h3>Clinics &amp; services</h3><p>FAQs, slot booking, reminders and a clean handoff to staff.</p></article>'
		.'<article class="site-page-card"><h3>Stores &amp; D2C</h3><p>Stock, cart, payment and CRM update from one conversation.</p></article>'
		.'<article class="site-page-card"><h3>Any WhatsApp shop</h3><p>If customers already message you, the AI can sell for you.</p></article>'
		.'</div></div></section>';
}

function site_contact_form_html($type = 'contact')
{
	$action = htmlspecialchars(base_url('contact/save'), ENT_QUOTES, 'UTF-8');

	$notice = '';
	$sent = isset($_GET['sent']) ? (string) $_GET['sent'] : '';
	if ($sent === '1') {
		$notice = '<div class="site-form-ok site-form-full">Thank you. We received your message and will reply shortly.</div>';
	} elseif ($sent === '0') {
		$notice = '<div class="site-form-err site-form-full">Please fill name, a valid email and a message, then try again.</div>';
	}

	return '<section class="site-page"><div class="site-page-wrap"><div class="site-page-split">'
		.'<aside class="site-page-aside"><div class="site-page-info">'
		.'<span class="site-page-kicker">Contact</span>'
		.'<h3>Talk to the team</h3>'
		.'<p>Questions, a demo, or getting started — send a message. We reply within 24 hours. No credit card to start.</p>'
		.'<dl>'
		.'<div><dt>Reply time</dt><dd>Within 24 hours</dd></div>'
		.'<div><dt>Channels</dt><dd>WhatsApp · Instagram · Facebook · Voice</dd></div>'
		.'<div><dt>Plans</dt><dd>Starter ₹999 · Growth ₹2,999 · Business ₹5,999</dd></div>'
		.'</dl></div></aside>'
		.'<div class="site-page-panel">'
		.'<h2>Get in touch</h2><p class="site-page-intro">Share your business and what you want the AI to handle.</p>'
		.'<form class="site-form" method="post" action="'.$action.'">'.$notice
		.'<div><label for="site-name">Name</label><input id="site-name" name="name" type="text" required maxlength="150"></div>'
		.'<div><label for="site-email">Email</label><input id="site-email" name="email" type="email" required maxlength="150"></div>'
		.'<div class="site-form-full"><label for="site-phone">Phone</label><input id="site-phone" name="phone" type="tel" maxlength="40"></div>'
		.'<div class="site-form-full"><label for="site-business">Business name</label><input id="site-business" name="business" type="text" maxlength="150"></div>'
		.'<div><label for="site-industry">Industry</label><select id="site-industry" name="industry">'
		.'<option value="">Select</option>'
		.'<option>Saree / boutique</option><option>Ecommerce / D2C</option><option>Clinic / services</option>'
		.'<option>Education</option><option>Real estate</option><option>Other</option>'
		.'</select></div>'
		.'<div><label for="site-subject">Subject</label><input id="site-subject" name="subject" type="text" maxlength="200"></div>'
		.'<div class="site-form-full"><label for="site-message">Message</label><textarea id="site-message" name="message" rows="6" required></textarea></div>'
		.'<button type="submit">Send Message</button>'
		.'</form></div></div></div></section>';
}

function ensure_contact_enquiries_table()
{
	$CI =& get_instance();
	$CI->load->database();
	if (!$CI->db) {
		return false;
	}

	if (!$CI->db->table_exists('contact_enquiries')) {
		$CI->db->query("CREATE TABLE IF NOT EXISTS `contact_enquiries` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`user_id` INT UNSIGNED NULL DEFAULT NULL,
			`name` VARCHAR(150) NOT NULL,
			`email` VARCHAR(150) NOT NULL,
			`phone` VARCHAR(40) NULL DEFAULT NULL,
			`business_name` VARCHAR(150) NULL DEFAULT NULL,
			`industry` VARCHAR(80) NULL DEFAULT NULL,
			`subject` VARCHAR(200) NULL DEFAULT NULL,
			`message` TEXT NOT NULL,
			`source` VARCHAR(20) NOT NULL DEFAULT 'web',
			`status` ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',
			`admin_note` TEXT NULL,
			`created_at` DATETIME NOT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_contact_email` (`email`),
			KEY `idx_contact_status` (`status`),
			KEY `idx_contact_created` (`created_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		return true;
	}

	foreach (array(
		'user_id'       => 'ADD COLUMN `user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`',
		'phone'         => 'ADD COLUMN `phone` VARCHAR(40) NULL DEFAULT NULL AFTER `email`',
		'business_name' => 'ADD COLUMN `business_name` VARCHAR(150) NULL DEFAULT NULL AFTER `phone`',
		'industry'      => 'ADD COLUMN `industry` VARCHAR(80) NULL DEFAULT NULL AFTER `business_name`',
		'subject'       => 'ADD COLUMN `subject` VARCHAR(200) NULL DEFAULT NULL AFTER `industry`',
		'source'        => 'ADD COLUMN `source` VARCHAR(20) NOT NULL DEFAULT \'web\' AFTER `message`',
		'admin_note'    => 'ADD COLUMN `admin_note` TEXT NULL AFTER `status`',
		'updated_at'    => 'ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL AFTER `created_at`',
	) as $col => $alter) {
		if (!$CI->db->field_exists($col, 'contact_enquiries')) {
			$CI->db->query('ALTER TABLE `contact_enquiries` '.$alter);
		}
	}

	return true;
}

function save_site_enquiry($source = 'web')
{
	$CI =& get_instance();
	$name = trim((string) $CI->input->post('name', true));
	$email = trim((string) $CI->input->post('email', true));
	$phone = trim((string) $CI->input->post('phone', true));
	$subject = trim((string) $CI->input->post('subject', true));
	$message = trim((string) $CI->input->post('message', true));
	$business = trim((string) $CI->input->post('business', true));
	$industry = trim((string) $CI->input->post('industry', true));

	$redirect = 'contact';
	if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		redirect($redirect.'?sent=0');
		return;
	}

	$subject = $subject !== '' ? substr($subject, 0, 200) : ($source === 'enquiry' ? 'Website enquiry' : 'Website contact');

	try {
		if (!ensure_contact_enquiries_table()) {
			throw new Exception('contact_enquiries table is not available');
		}

		$CI->db->insert('contact_enquiries', array(
			'name'          => substr($name, 0, 150),
			'email'         => substr($email, 0, 150),
			'phone'         => $phone !== '' ? substr($phone, 0, 40) : null,
			'business_name' => $business !== '' ? substr($business, 0, 150) : null,
			'industry'      => $industry !== '' ? substr($industry, 0, 80) : null,
			'subject'       => $subject,
			'message'       => $message,
			'source'        => 'web',
			'status'        => 'new',
			'created_at'    => date('Y-m-d H:i:s'),
		));
	} catch (Exception $e) {
		log_message('error', 'Landing enquiry save failed: '.$e->getMessage());
		redirect($redirect.'?sent=0');
		return;
	}

	$mailBody = $message;
	if ($business !== '') {
		$mailBody = "Business: {$business}\n".($industry !== '' ? "Industry: {$industry}\n" : '')."\n".$mailBody;
	}
	if ($phone !== '') {
		$mailBody = "Phone: {$phone}\n".$mailBody;
	}
	$mailBody = "Subject: {$subject}\n".$mailBody;

	$CI->load->helper('sk_mailer');
	sk_mail_contact_enquiry($name, $email, $mailBody);

	redirect($redirect.'?sent=1');
}

function site_legal_content_html($type)
{
	$contact = htmlspecialchars(base_url('contact'), ENT_QUOTES, 'UTF-8');
	$enquiry = htmlspecialchars(base_url('contact'), ENT_QUOTES, 'UTF-8');
	$privacy = htmlspecialchars(base_url('privacy'), ENT_QUOTES, 'UTF-8');
	$terms = htmlspecialchars(base_url('terms'), ENT_QUOTES, 'UTF-8');

	if ($type === 'privacy') {
		$body = '<p>Last updated: 4 September 2026</p>'
			.'<p>This Privacy Policy explains how this website and platform (“we”, “us”) collect, use, store and share information when you visit the site, send an enquiry, or use our WhatsApp, social media, voice and automation services.</p>'
			.'<h2>1. Information we collect</h2>'
			.'<ul>'
			.'<li>Account and enquiry details such as name, business name, phone number, email, industry and message content.</li>'
			.'<li>Customer conversation data that you connect to the platform, including WhatsApp, Instagram, Facebook, YouTube, website chat and voice call content, metadata, media and timestamps.</li>'
			.'<li>Business content you upload so the AI can answer, such as products, FAQs, pricing, availability, policies and CRM records.</li>'
			.'<li>Technical data such as IP address, browser type, device, pages visited and approximate location.</li>'
			.'</ul>'
			.'<h2>2. How we use information</h2>'
			.'<p>We use information to provide and improve the service: reply to customers, qualify leads, book appointments, send catalogues or payment links, update CRM records, hand off chats to your team, prevent abuse, and respond to your support requests.</p>'
			.'<h2>3. WhatsApp, Meta and other providers</h2>'
			.'<p>If you connect WhatsApp, Instagram, Facebook or similar channels, message traffic also flows through Meta and other network providers under their terms. Voice, AI model, payment gateway, hosting and CRM partners may process data as needed to run the feature you enable. We do not sell personal data.</p>'
			.'<h2>4. AI processing</h2>'
			.'<p>Conversation text and the knowledge you provide may be sent to AI models so the agent can understand intent and reply. Do not upload data you are not allowed to process. You remain responsible for notices and consents you owe your own customers.</p>'
			.'<h2>5. Cookies</h2>'
			.'<p>The website may use cookies or similar tools for session, security and basic analytics. You can block cookies in your browser; some site features may not work as expected.</p>'
			.'<h2>6. Retention</h2>'
			.'<p>We keep enquiry and account records as long as needed to provide the service, meet legal duties, and resolve disputes. Connected chat logs follow the retention settings of your workspace and the third-party channel.</p>'
			.'<h2>7. Your choices</h2>'
			.'<p>You may ask to access, correct or delete personal data we hold about you, or disconnect a channel. We may refuse a request where the law requires us to keep records. Contact us using the details below.</p>'
			.'<h2>8. Children</h2>'
			.'<p>The platform is for businesses. It is not directed at children, and we do not knowingly collect data from children.</p>'
			.'<h2>9. Changes</h2>'
			.'<p>We may update this policy. The “Last updated” date at the top will change when we do. Continued use of the site or service after an update means you accept the revised policy.</p>'
			.'<h2>10. Contact</h2>'
			.'<p>For privacy questions, use the <a href="'.$contact.'">Contact</a> page. Related terms are in our <a href="'.$terms.'">Terms and Conditions</a>.</p>';
	} else {
		$body = '<p>Last updated: 4 September 2026</p>'
			.'<p>These Terms and Conditions govern your use of this website and our WhatsApp, social media, AI chatbot, voice and automation platform. By using the site or starting a plan, you agree to these terms.</p>'
			.'<h2>1. Who this is for</h2>'
			.'<p>The service is offered to businesses and their authorised staff. You confirm you have authority to connect business channels such as WhatsApp Business, Instagram, Facebook, CRM, ecommerce and payment tools.</p>'
			.'<h2>2. The service</h2>'
			.'<p>We provide software that can capture leads, answer customers, qualify interest, book appointments, share products, collect payments where enabled, update CRM records, and hand off to a human. Features depend on the plan you choose and the channels you connect.</p>'
			.'<h2>3. Your responsibilities</h2>'
			.'<ul>'
			.'<li>Keep login details secure and use only accurate business information.</li>'
			.'<li>Comply with WhatsApp, Meta, Google, payment gateway and other provider policies, including opt-in, template and spam rules.</li>'
			.'<li>Obtain any consent required from your customers before messaging them or using their data in the AI.</li>'
			.'<li>Do not use the platform for illegal content, fraud, harassment, or unsolicited bulk messaging.</li>'
			.'</ul>'
			.'<h2>4. AI and results</h2>'
			.'<p>AI replies are generated automatically from the knowledge you provide and the conversation context. Outputs can be incomplete or incorrect. You must review high-value, medical, legal or financial conversations. We do not guarantee leads, conversions, uptime of third-party networks, or specific sales results.</p>'
			.'<h2>5. Plans, fees and third-party charges</h2>'
			.'<p>Starter, Growth and Business plan fees are billed as shown at purchase. WhatsApp/Meta conversation fees, AI usage, voice minutes and payment gateway charges may be billed separately by us or by the provider. Taxes may apply. Unpaid amounts may lead to suspension.</p>'
			.'<h2>6. Human handoff</h2>'
			.'<p>The AI is meant to handle repetitive questions and qualification. Complex, angry or high-value chats should be handled by your team. You are responsible for staffing that handoff.</p>'
			.'<h2>7. Intellectual property</h2>'
			.'<p>The website, software and branding remain ours. You keep rights in your own content, product data and customer lists, and grant us a licence to process them only to provide the service.</p>'
			.'<h2>8. Suspension and termination</h2>'
			.'<p>We may suspend or end access if you breach these terms, if a channel provider blocks the account, or if required by law. You may stop using the service at any time. Fees already paid are not refundable except where required by law or agreed in writing.</p>'
			.'<h2>9. Liability</h2>'
			.'<p>To the extent allowed by law, we are not liable for lost profits, lost data, missed leads, or outages of WhatsApp, Meta, voice or payment networks. Our total liability for a claim is limited to the subscription fees you paid us for the service in the three months before the claim.</p>'
			.'<h2>10. Privacy</h2>'
			.'<p>How we handle personal data is described in the <a href="'.$privacy.'">Privacy Policy</a>.</p>'
			.'<h2>11. Changes</h2>'
			.'<p>We may update these terms. The “Last updated” date will change when we do. Continued use after an update means you accept the revised terms.</p>'
			.'<h2>12. Contact</h2>'
			.'<p>Questions about these terms can be sent through <a href="'.$contact.'">Contact</a>.</p>';
	}

	return '<section class="site-page"><div class="site-legal-page"><div class="site-legal-inner">'.$body.'</div></div></section>';
}

function apply_legal_copy($html, $type)
{
	$title = ($type === 'privacy') ? 'Privacy Policy' : 'Terms and Conditions';

	return apply_page_chrome($html, $title, site_legal_content_html($type));
}

function render_template($file, $current = 'home', $slug = '')
{
	$path = APPPATH.'views'.DIRECTORY_SEPARATOR.'website'.DIRECTORY_SEPARATOR.$file;
	if (!is_file($path) && in_array($current, array('about', 'contact', 'enquiry', 'terms', 'privacy'), true)) {
		$file = 'service.html';
		$path = APPPATH.'views'.DIRECTORY_SEPARATOR.'website'.DIRECTORY_SEPARATOR.$file;
	}
	if (!is_file($path)) {
		show_404();
		return '';
	}

	$html = file_get_contents($path);
	if ($current === 'about') {
		$html = apply_page_chrome($html, 'About', site_about_content_html());
	}
	if ($current === 'contact') {
		$html = apply_page_chrome($html, 'Contact', site_contact_form_html('contact'));
	}
	if ($current === 'enquiry') {
		$html = apply_enquiry_copy($html);
	}
	if ($current === 'terms' || $current === 'privacy') {
		$html = apply_legal_copy($html, $current);
	}
	$html = apply_business_copy($html, $slug);

	return rewrite_template_html($html, $current);
}
