<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Marketing_site extends CI_Controller {

    private function render(array $data): void {
        $this->load->view('website/marketing_page', $data);
    }

    public function platform(): void {
        $this->render([
            'active' => 'platform',
            'eyebrow' => 'The complete platform',
            'title' => 'One operating system for every customer conversation',
            'lead' => 'Bring messaging, AI, automation, customer data, ecommerce actions and reporting into one practical workspace.',
            'sections' => [
                ['Unified Inbox', 'Handle WhatsApp, Instagram, Facebook, website and mobile app conversations together.', ['Conversation assignment', 'Internal notes and tags', 'Human handoff', 'Customer history']],
                ['AI Sales & Support Agent', 'Give customers accurate answers from your approved products, services, FAQs and policies.', ['Intent detection', 'Product recommendations', 'Lead qualification', 'Safe escalation']],
                ['Automation Builder', 'Connect triggers, conditions and actions to remove repetitive work from every customer journey.', ['Message triggers', 'Conditional routing', 'Follow-up sequences', 'Team notifications']],
                ['CRM & Customer 360', 'Keep identity, channel history, lead status, orders and team activity attached to each customer.', ['Lead pipeline', 'Segments and labels', 'Activity timeline', 'Custom attributes']],
                ['Commerce Actions', 'Move from product discovery to payment, invoice, shipping and reorder without losing the chat.', ['Catalogue search', 'Cart recovery', 'Payment links', 'Order tracking']],
                ['Analytics & Control', 'Understand response quality, workload, lead conversion, campaigns and automation outcomes.', ['Live dashboards', 'Campaign reports', 'Agent performance', 'Audit trail']],
            ],
            'steps_title' => 'The platform core',
            'steps' => [
                ['Connect', 'Connect business channels, customer data and your team.'],
                ['Understand', 'AI reads intent and retrieves approved business knowledge.'],
                ['Automate', 'Workflows reply, update records and trigger the next action.'],
                ['Convert', 'Your team receives qualified opportunities with full context.'],
            ],
        ]);
    }

    public function channels(): void {
        $this->render([
            'active' => 'channels',
            'eyebrow' => 'Omnichannel conversations',
            'title' => 'One inbox across every customer channel',
            'lead' => 'Serve customers consistently on the channels they already use while maintaining one customer history.',
            'sections' => [
                ['WhatsApp Business', 'Automate replies, templates, campaigns, product conversations and order updates.', ['Cloud API inbox', 'Approved templates', 'Broadcast campaigns', 'AI replies']],
                ['Instagram', 'Turn comments, story replies and direct messages into leads and support cases.', ['Comment automation', 'DM workflows', 'Lead capture', 'Human handoff']],
                ['Facebook', 'Bring Messenger and page engagement into the same operating workflow.', ['Messenger inbox', 'Comment response', 'Contact matching', 'Team routing']],
                ['YouTube', 'Identify intent in comments and move interested viewers into a managed conversation.', ['Comment monitoring', 'Intent detection', 'Lead routing', 'Response workflows']],
                ['Website Chat', 'Add an always-on AI assistant to your site for discovery, qualification and support.', ['Knowledge answers', 'Lead forms', 'Live handoff', 'Conversation history']],
                ['Mobile App', 'Connect in-app enquiries, support events and notifications to the same customer record.', ['In-app support', 'Push notifications', 'User context', 'Event triggers']],
            ],
            'steps_title' => 'One customer, one continuous history',
            'steps' => [
                ['Receive', 'A message, comment or app event enters the shared inbox.'],
                ['Match', 'Talk AI Pilot finds or creates the customer record.'],
                ['Respond', 'AI or a team member continues with complete context.'],
                ['Measure', 'The outcome is recorded for reporting and follow-up.'],
            ],
        ]);
    }

    public function solutions(): void {
        $this->render([
            'active' => 'solutions',
            'eyebrow' => 'Solutions by outcome',
            'title' => 'Automate the journeys that make your business move',
            'lead' => 'Start with sales, support, marketing or operations and expand from one workflow to a complete customer automation layer.',
            'sections' => [
                ['AI Sales', 'Qualify enquiries, recommend the right offer and move warm opportunities to your sales team.', ['Lead qualification', 'Product matching', 'Follow-up reminders', 'Sales routing']],
                ['Customer Support', 'Resolve frequent questions immediately and protect your team’s time for complex issues.', ['FAQ resolution', 'Ticket classification', 'Status updates', 'Escalation rules']],
                ['Campaigns', 'Reach opted-in audiences with relevant updates, launches, reminders and offers.', ['Audience segments', 'Template messaging', 'Scheduled sends', 'Campaign reports']],
                ['Ecommerce', 'Support discovery, checkout, payment, order tracking, returns and repeat purchase.', ['Product catalogue', 'Abandoned cart', 'Payment follow-up', 'Reorder automation']],
                ['Appointments', 'Collect requirements, suggest availability, confirm bookings and reduce no-shows.', ['Lead forms', 'Slot booking', 'Confirmations', 'Reminders']],
                ['Operations', 'Trigger staff tasks and customer notifications when important business events occur.', ['Internal alerts', 'SLA routing', 'Status workflows', 'Audit history']],
            ],
            'steps_title' => 'Launch one high-value journey first',
            'steps' => [
                ['Choose', 'Identify the repetitive journey with the clearest business value.'],
                ['Design', 'Map the trigger, questions, knowledge and handoff rule.'],
                ['Launch', 'Connect the channel and activate the controlled workflow.'],
                ['Improve', 'Use conversation outcomes to refine and expand automation.'],
            ],
        ]);
    }

    public function services(): void {
        $this->render([
            'active' => 'services',
            'page_variant' => 'services',
            'eyebrow' => 'Talk AI Pilot services',
            'title' => 'From disconnected messages to a working automation system',
            'lead' => 'Our team designs and implements the channels, AI knowledge, workflows and integrations required to make customer automation useful in day-to-day operations.',
            'sections' => [
                ['WhatsApp Automation', 'Connect WhatsApp Business and automate enquiries, qualification, follow-ups and customer updates.', ['Cloud API setup', 'Template configuration', 'Inbox and routing', 'Automation launch']],
                ['Social Conversation Automation', 'Bring Instagram, Facebook and YouTube engagement into a managed lead and support workflow.', ['Comment workflows', 'Direct message routing', 'Lead capture', 'Cross-channel history']],
                ['AI Sales Agent', 'Create an assistant that understands your products, services, policies and qualification process.', ['Knowledge preparation', 'Intent design', 'Recommendation logic', 'Human escalation']],
                ['Customer Support Automation', 'Resolve repeat questions quickly while routing sensitive or complex cases to the right person.', ['FAQ automation', 'Case classification', 'SLA routing', 'Status notifications']],
                ['Ecommerce Integration', 'Connect product discovery, inventory, cart, payment, orders, invoices and shipping to conversations.', ['Catalogue connection', 'Cart recovery', 'Payment workflow', 'Order notifications']],
                ['CRM & Workflow Setup', 'Turn conversations into structured leads, segments, activities, tasks and measurable outcomes.', ['Customer 360', 'Lead pipeline', 'Team assignment', 'Reports and dashboards']],
            ],
            'steps_title' => 'How service delivery works',
            'steps' => [
                ['Discover', 'Understand channels, customer questions, data and team responsibilities.'],
                ['Design', 'Map the conversation, AI knowledge, automation and handoff points.'],
                ['Implement', 'Connect the required systems and configure the controlled workflow.'],
                ['Launch', 'Test with your team, activate the service and improve from real outcomes.'],
            ],
            'service_outcomes' => [
                ['Faster response', 'Customers receive an immediate, useful first response—even outside business hours.'],
                ['Cleaner handoff', 'Staff receive qualified conversations with history, intent and the next action.'],
                ['Less manual work', 'Follow-ups, status messages, routing and repetitive updates happen automatically.'],
            ],
        ]);
    }

    public function pricing(): void {
        $this->render([
            'active' => 'pricing',
            'eyebrow' => 'Simple plans',
            'title' => 'Start with the channels and automation you need',
            'lead' => 'Choose a foundation for your team. Messaging provider fees, AI usage and optional integrations may vary by usage.',
            'pricing' => [
                ['Starter', 'For a small team launching its first automated channel.', '₹999', ['1 business channel', 'Shared team inbox', 'Basic AI replies', 'Core automation', 'Email support']],
                ['Growth', 'For growing teams managing leads, campaigns and support.', '₹2,999', ['3 business channels', 'AI knowledge base', 'Advanced workflows', 'Campaign messaging', 'Reports and CRM']],
                ['Business', 'For multi-team operations with custom journeys and control.', 'Talk to us', ['All supported channels', 'Custom automation', 'Roles and routing', 'Commerce integration', 'Priority onboarding']],
            ],
            'steps_title' => 'Every plan includes',
            'steps' => [
                ['Onboarding', 'Guidance to connect your first channel and workflow.'],
                ['Security', 'Controlled access and protected business configuration.'],
                ['Handoff', 'Move from AI to your staff with full conversation context.'],
                ['Visibility', 'Track conversations and automation activity.'],
            ],
        ]);
    }

    public function about(): void {
        $this->render([
            'active' => 'about',
            'page_variant' => 'about',
            'eyebrow' => 'About Talk AI Pilot',
            'title' => 'We turn customer conversations into organised business action',
            'lead' => 'Talk AI Pilot was created for businesses that have leads and support requests spread across messaging apps, social channels, websites and mobile products.',
            'sections' => [
                ['Our Mission', 'Help businesses respond faster, operate consistently and create better customer journeys with responsible automation.', ['Practical AI', 'Clear business outcomes', 'Human control', 'Continuous improvement']],
                ['What We Build', 'A unified platform for conversations, customer context, automation, commerce and team collaboration.', ['Messaging channels', 'AI agents', 'Workflow automation', 'Operational reporting']],
                ['How We Work', 'We begin with a real journey, connect the required data, and keep human handoff at the centre.', ['Understand first', 'Launch safely', 'Measure outcomes', 'Improve together']],
            ],
            'steps_title' => 'Our product principles',
            'steps' => [
                ['Useful', 'Automation must solve a real customer or team problem.'],
                ['Clear', 'Teams should understand what the AI did and why.'],
                ['Controlled', 'People remain responsible for sensitive and valuable moments.'],
                ['Connected', 'Conversation data should drive the next business action.'],
            ],
            'about_story' => [
                'Customers expect quick answers, but most teams work across separate inboxes, spreadsheets, ecommerce tools and manual follow-up lists.',
                'We built Talk AI Pilot to connect those pieces. AI handles repetitive conversation work, automation moves the process forward, and people remain in control of valuable or sensitive moments.',
            ],
            'about_stats' => [
                ['6+', 'customer channels'],
                ['24/7', 'AI availability'],
                ['1', 'shared customer history'],
                ['100%', 'human handoff control'],
            ],
        ]);
    }

    public function contact(): void {
        $this->render([
            'active' => 'contact',
            'page_variant' => 'contact',
            'eyebrow' => 'Talk to our team',
            'title' => 'Let’s map the right automation for your business',
            'lead' => 'Tell us where customer conversations arrive, what your team handles manually and which outcome matters most. We will recommend a practical first workflow.',
            'contact_form' => true,
            'contact_topics' => [
                ['01', 'Channel setup', 'WhatsApp, Instagram, Facebook, YouTube, website or mobile app.'],
                ['02', 'AI and workflow', 'Knowledge, qualification, routing, follow-up and human handoff.'],
                ['03', 'Business integration', 'CRM, ecommerce, payments, orders, shipping and reporting.'],
            ],
        ]);
    }

    public function privacy(): void {
        $this->render([
            'active' => '',
            'eyebrow' => 'Legal',
            'title' => 'Privacy Policy',
            'lead' => 'How Talk AI Pilot collects, uses and protects information across the website and connected communication services.',
            'legal_html' => site_legal_content_html('privacy'),
        ]);
    }

    public function terms(): void {
        $this->render([
            'active' => '',
            'eyebrow' => 'Legal',
            'title' => 'Terms and Conditions',
            'lead' => 'The terms governing use of the Talk AI Pilot website, AI agents, channels and automation platform.',
            'legal_html' => site_legal_content_html('terms'),
        ]);
    }
}
