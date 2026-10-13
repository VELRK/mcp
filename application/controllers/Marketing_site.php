<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Marketing_site extends CI_Controller {

    private function render(array $data): void {
        $this->load->view('website/marketing_page', $data);
    }

    public function platform(): void {
        $this->render([
            'active' => 'platform',
            'eyebrow' => 'Autonomous Robotic AI Platform',
            'title' => 'One Intelligent Operating System for Every Customer Conversation',
            'lead' => 'Connect WhatsApp Cloud API, Instagram DMs, Facebook, YouTube & Web Chat into one robotic brain. Autonomous 0.4s replies, catalog search, in-chat payments, PDF invoices, and seamless human handoff.',
            'hero_img' => base_url('assets/images/website/platform_ai_core.jpg'),
            'hero_img_alt' => 'Talk AI Pilot Robotic AI Cybernetic Core OS & Inventory Matching Dashboard',
            'sections' => [
                [
                    'Autonomous Multi-Channel AI Agent',
                    'Deliver human-grade auto-replies across WhatsApp, Instagram, Messenger, and Web with sub-second latency.',
                    ['Sub-second response speed (< 0.4s)', 'Natural multi-lingual NLP (50+ languages & Hinglish)', 'Live product catalog and stock matching', 'Intent classification & smart lead qualification']
                ],
                [
                    'In-Chat Commerce & Instant Payments',
                    'Turn messaging threads into high-converting checkout experiences with zero website redirection.',
                    ['Interactive product cards with photos and variants', 'Razorpay, UPI (GPay/PhonePe) & card payment links', 'Automated branded PDF invoices on WhatsApp', 'Instant payment verification & order placement']
                ],
                [
                    'Visual Automation & Flow Builder',
                    'Design intuitive conversational logic with triggers, conditions, branching rules, and external webhooks.',
                    ['Drag-and-drop conversational triggers', 'Conditional logic (by cart value, user tag, or channel)', 'Meta Business Agent tool integration', 'Automated follow-up drip sequences & reminders']
                ],
                [
                    'Unified Team Inbox & Human Takeover',
                    'Give your agents complete context across all channels with instant 1-click human intervention.',
                    ['Unified inbox across WhatsApp, IG, FB & Web', '100% controlled human handoff with thread locking', 'Internal notes, team assignments (@mentions) & tags', 'Multi-branch isolation for enterprise teams']
                ],
                [
                    'Omnichannel CRM 360 & Identity Stitching',
                    'Consolidate customer identity, order history, tags, notes, and activity timeline across every platform.',
                    ['Cross-channel customer profile merging', 'Automated lead scoring & VIP tagging', 'Complete conversation & purchase history', 'Custom attributes, segments & export capabilities']
                ],
                [
                    'Real-Time Analytics & Conversion Control',
                    'Track response quality, AI resolution rate, revenue generated per channel, and team performance.',
                    ['Real-time resolution & CSAT metrics', 'Revenue & in-chat payment reports', 'Agent workload & response time tracking', 'Full audit logging & compliance reports']
                ],
            ],
            'steps_title' => 'How the Robotic Platform Architecture Operates',
            'steps' => [
                ['Ingest', 'Inbound message, comment, or app event arrives via cloud webhook in < 50ms.'],
                ['Analyze', 'Robotic NLP engine classifies intent, parses language, and retrieves live inventory & FAQs.'],
                ['Execute', 'Autonomous agent auto-replies in 0.4s, presents product cards, or generates UPI payment links.'],
                ['Handoff', 'Leads sync with CRM 360, and complex moments gracefully transfer to staff with full context.'],
            ],
            'service_outcomes' => [
                ['< 0.4s Response Time', 'Customers receive instant, accurate answers 24/7 without waiting in operator queues.'],
                ['3.8x Conversion Lift', 'Shoppers discover products, pay via UPI, and receive invoices directly in chat.'],
                ['65% Cost Reduction', 'Robotic AI resolves repetitive inquiries, letting your human team focus on VIP deals.'],
            ]
        ]);
    }

    public function channels(): void {
        $this->render([
            'active' => 'channels',
            'eyebrow' => 'Connected Omnichannel Ecosystem',
            'title' => 'One Robotic Brain Synced Across Every Channel Your Customers Use',
            'lead' => 'Serve customers with flawless consistency on WhatsApp, Instagram, Facebook Messenger, YouTube, Website Live Chat, and Mobile Apps — sharing one continuous customer record.',
            'hero_img' => base_url('assets/images/website/channels_ai_hub.jpg'),
            'hero_img_alt' => 'Talk AI Pilot Omnichannel Robotic Command Center - WhatsApp, Instagram, Messenger, YouTube & Live Chat',
            'sections' => [
                [
                    'Official WhatsApp Business Cloud API',
                    'Direct Meta Cloud API integration with maximum message deliverability, blue badge readiness, and zero ban risk.',
                    ['Official Cloud API inbox with 24-hour window sync', 'Pre-approved marketing & utility template broadcasts', 'In-chat catalog browsing, payments & PDF invoices', 'High-speed automated AI replies 24/7']
                ],
                [
                    'Instagram DMs, Stories & Reels Comments',
                    'Turn viral social engagement into revenue by auto-replying to reel comments, story mentions, and direct messages.',
                    ['Instant comment-to-DM automated response', 'Story mention lead capture & coupon distribution', 'VIP influencer and brand inquiry routing', '1-click handoff to sales specialists']
                ],
                [
                    'Facebook Messenger & Page Engagement',
                    'Unify Messenger inquiries and post engagement into the same automated sales and support engine.',
                    ['Automated Messenger sales copilot', 'Page post comment auto-responder', 'Meeting scheduling & calendar booking', 'Unified customer matching with WhatsApp records']
                ],
                [
                    'YouTube Video Comment Lead Capture',
                    'Detect buyer intent in video comments, generate automated replies with tracking links, and route viewers into chat.',
                    ['Automated comment intent detection', 'Lead qualification & WhatsApp link routing', 'Product recommendation links in replies', 'Real-time click & conversion tracking']
                ],
                [
                    'Website Live AI Chatbot Widget',
                    'Embed an intelligent robotic copilot on your website for instant product discovery, checkout assistance, and support.',
                    ['Dynamic product search & live cart integration', 'Smart lead capture forms & booking tools', 'Instant human handoff switch with full chat transcript', 'Custom branded floating widget']
                ],
                [
                    'Mobile App SDK (iOS & Android)',
                    'Connect native in-app messaging, push notifications, and customer events to the exact same customer record.',
                    ['Native iOS and Android SDK support', 'Push notification sync & order status triggers', 'In-app support ticket resolution', 'Full context shared with WhatsApp & Web']
                ],
            ],
            'steps_title' => 'One Customer, One Continuous History Across All Channels',
            'steps' => [
                ['Receive', 'A message, comment, or app event enters the shared multi-channel webhook gateway.'],
                ['Match', 'Talk AI Pilot matches identity across phone, email, and social handle into one CRM profile.'],
                ['Respond', 'Robotic AI delivers a context-aware response, catalog card, or payment link in < 0.4s.'],
                ['Measure', 'The conversation outcome and revenue generated are logged in real-time dashboards.'],
            ],
            'service_outcomes' => [
                ['Zero Channel Fragmentation', 'Never lose context when a customer moves from an Instagram reel to WhatsApp.'],
                ['Universal 24/7 Availability', 'Your brand responds across all time zones in less than half a second.'],
                ['Unified Reporting', 'Compare lead conversion and customer acquisition cost across every channel.']
            ]
        ]);
    }

    public function solutions(): void {
        $this->render([
            'active' => 'solutions',
            'eyebrow' => 'Industry Solutions & Blueprints',
            'title' => 'Purpose-Built Automation for High-Growth Business Models',
            'lead' => 'From retail e-commerce to consulting, travel, education, and multi-branch retail — deploy robotic AI tailored to your exact industry workflows.',
            'hero_img' => base_url('assets/images/website/solutions_automation.jpg'),
            'hero_img_alt' => 'Talk AI Pilot Multi-Industry Automation & In-Chat Commerce Operations Cockpit',
            'sections' => [
                [
                    'E-Commerce & D2C Brands',
                    'Automate product discovery, abandoned cart recovery, in-chat payments, and shipping updates.',
                    ['Interactive catalog browsing inside WhatsApp & IG', 'Razorpay & UPI instant payment links', 'Automated PDF invoice generation and delivery', 'Live order tracking with BlueDart & Delhivery webhooks']
                ],
                [
                    'Professional Consulting & B2B Services',
                    'Automate client inquiries, discovery call scheduling, consultation reminders, and service proposals.',
                    ['Client requirements qualification', 'Discovery call scheduling with Google/Outlook calendar sync', 'Automated meeting reminders to reduce no-shows', 'Proposal & quotation delivery via WhatsApp']
                ],
                [
                    'Travel, Tourism & Hospitality',
                    'Engage travelers 24/7, share holiday itineraries, filter packages by budget & dates, and capture bookings.',
                    ['Instant tour itinerary & brochure downloads', 'Budget & travel dates preference filtering', 'Reservation inquiry booking with automated reminders', 'Lead distribution to dedicated travel planners']
                ],
                [
                    'Education, Coaching & EdTech',
                    'Capture student inquiries, share course syllabi, verify prerequisites, and collect course fees.',
                    ['Course syllabus & fee structure automation', 'Demo class registration & Zoom link delivery', 'Scholarship eligibility screening', 'Fee payment links & automated receipts']
                ],
                [
                    'Local Services, Salons & Retail',
                    'Provide instant pricing, service menu lookups, reservation scheduling, and customer re-engagement.',
                    ['Service menu and price list sharing in chat', 'Slot booking & technician assignment', 'Post-service review requests with Google Review links', 'Festive offer broadcasts to loyal customers']
                ],
                [
                    'Multi-Branch & Enterprise Retail',
                    'Deploy dedicated workspaces for multiple store locations with regional numbers and unified inbox management.',
                    ['Multi-outlet store routing & department logins', 'Branch-specific product catalogs & order routing', 'Centralized business performance analytics', 'Role-based access control & enterprise governance']
                ],
            ],
            'steps_title' => 'How to Deploy Your Industry Blueprint in 4 Steps',
            'steps' => [
                ['Select', 'Choose the blueprint matching your business model (Retail, Consulting, Travel, SaaS).'],
                ['Sync', 'Connect your catalog, pricing, calendar, or inventory database with one click.'],
                ['Activate', 'Link your official WhatsApp number and social channels to go live.'],
                ['Scale', 'Track in-chat orders, qualification rate, and revenue on your live dashboard.'],
            ]
        ]);
    }

    public function pricing(): void {
        $this->render([
            'active' => 'pricing',
            'eyebrow' => 'Transparent & Predictable Pricing',
            'title' => 'Start Free. Scale Your Conversational Revenue with High ROI',
            'lead' => 'Choose a foundation designed for your team size and message volume. Official Meta WhatsApp Cloud API connectivity, in-chat commerce, and human handoff included in every plan.',
            'pricing' => [
                [
                    'Starter',
                    'Perfect for small businesses and stores launching their first automated channel.',
                    '₹999',
                    [
                        '1 Business Channel (WhatsApp or Web)',
                        'Shared Team Inbox (2 Agent Seats)',
                        'Robotic AI Auto-Replies (2,000 chats/mo)',
                        'Product Catalog Integration',
                        'Razorpay UPI Payment Links',
                        'Standard Email Support',
                        '99.9% Uptime Guarantee'
                    ]
                ],
                [
                    'Growth',
                    'For scaling brands managing leads across WhatsApp, Instagram & Web.',
                    '₹2,999',
                    [
                        '3 Business Channels (WhatsApp, IG, Web)',
                        'Shared Team Inbox (5 Agent Seats)',
                        'Robotic AI Auto-Replies (10,000 chats/mo)',
                        'Advanced Visual Flow Builder',
                        'In-Chat Payments & Automated PDF Invoices',
                        'WhatsApp Template Broadcast Campaigns',
                        'CRM 360 & Conversion Analytics',
                        'Priority WhatsApp Support'
                    ]
                ],
                [
                    'Business',
                    'For multi-team operations with high-volume omnichannel workflows & commerce.',
                    '₹5,999',
                    [
                        'All 6+ Supported Channels (WhatsApp, IG, FB, YT, Web, App)',
                        'Unlimited Agent Seats with Role Permissions',
                        'Unlimited Robotic AI Auto-Replies',
                        'Meta Business Agent Connectors',
                        'Multi-Branch Isolation (Enterprise Mode)',
                        'Dedicated Account Manager & Training',
                        'Custom ERP / Shopify / Custom Webhook Sync',
                        '99.99% Enterprise SLA'
                    ]
                ],
            ],
            'steps_title' => 'Every Talk AI Pilot Plan Includes as Standard',
            'steps' => [
                ['Official Meta API', 'Direct connectivity to Meta WhatsApp Cloud API with verified infrastructure.'],
                ['Data Security', '256-bit SSL encryption, ISO 27001 alignment, and isolated tenant databases.'],
                ['Human Handoff', 'Instant 1-click takeover with full conversation context and staff notifications.'],
                ['Analytics', 'Comprehensive conversion, CSAT, latency, and team performance dashboards.'],
            ],
            'service_outcomes' => [
                ['Zero Lock-In', 'Cancel or upgrade your subscription anytime with 1-click billing management.'],
                ['Official Meta Rates', 'Direct conversation fees paid to Meta at official baseline partner rates.'],
                ['Free Onboarding', 'Our architects assist you in connecting your first channel and setting up your AI knowledge base.']
            ]
        ]);
    }

    public function about(): void {
        $this->render([
            'active' => 'about',
            'page_variant' => 'about',
            'eyebrow' => 'About Talk AI Pilot',
            'title' => 'We Transform Fragmented Customer Chats into Autonomous Business Revenue',
            'lead' => 'Talk AI Pilot was founded to solve a fundamental problem: businesses lose millions in sales because customer inquiries arrive across WhatsApp, Instagram, and websites faster than human teams can reply.',
            'hero_img' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1200&q=80',
            'hero_img_alt' => 'Talk AI Pilot Engineering Team',
            'sections' => [
                [
                    'Our Mission',
                    'Empower businesses of all sizes to engage customers instantly, operate with 24/7 consistency, and drive conversational commerce with responsible AI.',
                    ['Sub-second auto-replies', 'Measurable revenue outcomes', 'Human control at every step', 'Direct Meta Cloud API architecture']
                ],
                [
                    'What We Build',
                    'A unified operating system combining omnichannel messaging, autonomous robotic AI, e-commerce checkout, and team CRM.',
                    ['WhatsApp Cloud API infrastructure', 'Social comment-to-DM pipelines', 'Visual workflow automation', 'In-chat payments & auto PDF invoicing']
                ],
                [
                    'How We Operate',
                    'We believe in transparency, security, and measurable ROI. Your data stays securely in your database, and your team retains complete control.',
                    ['Zero unofficial scrapers', 'Enterprise data sovereignty', 'Continuous intent tuning', 'Customer success partnership']
                ],
            ],
            'steps_title' => 'Our Core Product Principles',
            'steps' => [
                ['Autonomous First', 'Repetitive questions (catalog, price, stock, order status) must resolve in < 0.4 seconds.'],
                ['Human Centered', 'Staff retain 100% control with 1-click handoff, internal notes, and thread ownership.'],
                ['Commerce Native', 'Don’t just chat — show product cards, collect UPI payments, and deliver invoices in-thread.'],
                ['Secure & Compliant', 'Operate exclusively on verified Meta Cloud APIs with ISO 27001 security standards.'],
            ],
            'about_story' => [
                'Customers expect immediate answers whether they message at 2 PM on a weekday or 2 AM on a Sunday. Yet most businesses struggle across disconnected phones, WhatsApp Web, Instagram DM tabs, spreadsheets, and manual payment links.',
                'We built Talk AI Pilot to connect those broken pieces. Our autonomous robotic AI agent reads customer intent, checks your live catalog, replies in 0.4s, and sends instant payment links. When a high-value or complex query arises, it gracefully hands over to your team with full history.',
                'Today, Talk AI Pilot powers over 10 million automated conversations for e-commerce brands, consulting firms, travel agencies, and retailers across India and globally.'
            ],
            'about_stats' => [
                ['10M+', 'conversations automated'],
                ['0.38s', 'average response latency'],
                ['6+', 'synchronized channels'],
                ['99.99%', 'cloud platform uptime'],
            ],
        ]);
    }

    public function services(): void {
        $this->render([
            'active' => 'services',
            'page_variant' => 'services',
            'eyebrow' => 'Bespoke Implementation & Engineering Services',
            'title' => 'Turnkey AI Setup, Custom Flow Building & Enterprise Integration',
            'lead' => 'Our certified conversational AI architects design, train, connect, and launch your multi-channel automation so you achieve maximum ROI with zero technical friction.',
            'hero_img' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1200&q=80',
            'hero_img_alt' => 'Certified Conversational AI Implementation Specialists',
            'sections' => [
                [
                    'WhatsApp Business Cloud API Setup',
                    'End-to-end Meta Business Manager verification, phone number migration, display name approvals, and official Cloud API provisioning.',
                    ['Meta Business verification guidance', 'Green badge (verified tick) application support', 'High-throughput Cloud API connection', 'Approved marketing & utility templates setup']
                ],
                [
                    'Robotic AI Knowledge Base & Catalog Ingestion',
                    'We prepare, structure, and embed your product catalog, FAQs, pricing rules, and policies for high-accuracy intent matching.',
                    ['Product catalog structuring & variant sync', 'Multi-lingual FAQ preparation (50+ languages & Hinglish)', 'Intent and fallback logic engineering', 'Safe human escalation rule definition']
                ],
                [
                    'Social Conversation Automation (Instagram & FB)',
                    'Configure viral comment-to-DM triggers for reels and posts, story mention auto-replies, and influencer routing.',
                    ['Automated reel comment-to-DM workflows', 'Story mention lead capture & coupon triggers', 'Facebook page comment response pipelines', 'Cross-channel identity matching with CRM']
                ],
                [
                    'E-Commerce & Payment Gateway Integration',
                    'Connect your online store (Shopify, WooCommerce, custom DB) with Razorpay, Stripe, and UPI for in-chat checkouts.',
                    ['Real-time product stock & price sync', 'Razorpay & UPI payment link generation', 'Automated PDF invoice generation & dispatch', 'Shipping webhook integration (BlueDart, Delhivery)']
                ],
                [
                    'Complex Flow Building & Webhook Engineering',
                    'Design advanced multi-step workflows with conditional branching, external CRM sync, and automated staff alerts.',
                    ['Visual workflow builder configuration', 'External CRM & ERP webhook routing', 'Abandoned cart & follow-up drip sequences', 'Custom lead scoring & SLA escalations']
                ],
                [
                    'Staff Training & SLA Maintenance',
                    'Empower your team with live training on the unified inbox, human takeover best practices, and dedicated ongoing support.',
                    ['Agent training sessions & operating guides', 'Weekly intent tuning & conversation audits', 'Dedicated WhatsApp support channel', 'Guaranteed enterprise SLA & 99.99% uptime']
                ],
            ],
            'steps_title' => 'Our 4-Stage Turnkey Implementation Roadmap',
            'steps' => [
                ['Discover (01)', 'Audit your channels, customer questions, catalog structure, and staff responsibilities.'],
                ['Design (02)', 'Map the conversational journeys, curate knowledge embeddings, and define handoff points.'],
                ['Implement (03)', 'Connect Meta Cloud API, build flows, configure payment links, and test with your team.'],
                ['Launch (04)', 'Activate live 24/7 robotic auto-reply, monitor response accuracy, and optimize conversion.'],
            ],
            'service_outcomes' => [
                ['Sub-Second First Response', 'Every customer receives an immediate, helpful response even at 2 AM on holidays.'],
                ['Flawless Team Handoff', 'Your agents receive qualified leads with full context, intent, and suggested actions.'],
                ['Zero Operational Overhead', 'Catalog updates, payments, invoices, and follow-ups happen completely automatically.'],
            ],
        ]);
    }

    public function contact(): void {
        $this->render([
            'active' => 'contact',
            'page_variant' => 'contact',
            'eyebrow' => 'Connect With Our Architects',
            'title' => 'Let’s Map the Perfect Robotic Automation for Your Business',
            'lead' => 'Tell us which channels your customers reach out on and what processes you want to automate. We’ll demonstrate a live working prototype tailored to your brand.',
            'contact_form' => true,
            'contact_topics' => [
                ['01', 'Channel Architecture', 'Connect WhatsApp Business Cloud API, Instagram DMs, Facebook Messenger, YouTube, Website Chat or Mobile App.'],
                ['02', 'Robotic AI & Conversational Commerce', 'Custom product catalog sync, Razorpay/UPI payment links, automated PDF invoices, and intelligent human handoff.'],
                ['03', 'Enterprise & Multi-Vendor Systems', 'Custom ERP integration, multi-agent unified inbox routing, CRM 360, and dedicated SLA architecture.'],
            ],
            'service_outcomes' => [
                ['Direct WhatsApp Access', 'Prefer chatting? Message our solution architect directly on WhatsApp for instant guidance.'],
                ['< 2-Hour Response Time', 'Our team reviews your submission and responds with a customized workflow recommendation.'],
                ['Free Live Architecture Demo', 'Experience an interactive multi-channel demo pre-loaded with your brand’s products.']
            ]
        ]);
    }

    public function privacy(): void {
        $this->render([
            'active' => '',
            'eyebrow' => 'Legal & Compliance',
            'title' => 'Privacy Policy',
            'lead' => 'How Talk AI Pilot collects, uses and protects information across our website and connected communication services.',
            'legal_html' => site_legal_content_html('privacy'),
        ]);
    }

    public function terms(): void {
        $this->render([
            'active' => '',
            'eyebrow' => 'Legal & Compliance',
            'title' => 'Terms and Conditions',
            'lead' => 'The terms governing the use of the Talk AI Pilot website, robotic AI agents, channels and omnichannel automation platform.',
            'legal_html' => site_legal_content_html('terms'),
        ]);
    }

    public function refund(): void {
        $this->render([
            'active' => '',
            'eyebrow' => 'Legal & Compliance',
            'title' => 'Cancellation & Refund Policy',
            'lead' => 'Information on subscription cancellations, billing cycles, and refund eligibility for Talk AI Pilot services.',
            'legal_html' => site_legal_content_html('refund'),
        ]);
    }
}
