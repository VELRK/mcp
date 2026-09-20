# Project instructions for this ecommerce + WhatsApp AI integration

## Core principle

This repository is an existing PHP ecommerce application. Do not rewrite working business logic.

When implementing AI, WhatsApp, TalkAI, or MCP features, follow this order:

1. Reuse the existing ecommerce services already in this project.
2. Reuse the existing WhatsApp webhook and message sending flow.
3. Add only the missing integration layer.
4. Keep tenant scoping and trust boundaries strict.
5. Never allow the AI to directly access MySQL.

## Business architecture

The correct flow is:

Customer -> WhatsApp -> Meta Business Agent -> TalkAI API -> Tenant Resolver -> MCP -> Existing PHP Ecommerce -> MySQL -> MCP result -> TalkAI -> Meta Business Agent -> WhatsApp response

Never reverse these responsibilities.

## Required constraints

- Meta Business Agent handles customer-facing conversation and tone.
- TalkAI handles auth, tenant resolution, conversation context, tool routing, and validation.
- MCP exposes safe business tools only.
- PHP ecommerce remains the source of truth for products, prices, stock, customers, carts, orders, payments, shipping, tax, invoices, and delivery.
- MySQL is authoritative data storage and must not be accessed directly by the AI layer.
- Do not create duplicate APIs or duplicate database tables unless absolutely required.
- Do not rebuild working ecommerce code.

## Tenant security

- Never trust a tenant ID supplied by the AI or customer.
- Resolve tenant using trusted business metadata such as WhatsApp business account or phone_number_id.
- Every business operation must be tenant-scoped internally.
- If a tool is called without a trusted tenant context, reject it.

## WhatsApp / webhook handling

- Reuse the existing WhatsApp webhook implementation rather than replacing it.
- Normalize inbound WhatsApp payloads into a consistent structure before business processing.
- Treat message_id as an idempotency key.
- Ignore duplicate webhook events.

## MCP tool discipline

When adding MCP tools, keep them limited to safe business capabilities such as:

- search_products
- get_product
- get_product_variants
- check_stock
- get_customer
- save_customer
- create_cart
- add_to_cart
- update_cart
- calculate_cart
- calculate_shipping
- calculate_tax
- create_order
- get_order
- get_order_status
- create_payment_link
- get_payment_status
- generate_invoice
- get_delivery_status
- human_handoff

All functions should return:

- success: true/false
- data: structured facts only
- error: structured error details when needed

## Product and inventory rules

- Check real inventory from the existing PHP ecommerce inventory logic.
- Never allow the AI to estimate, guess, or invent stock.
- Product search should return factual product names, variants, price, and availability.
- The agent may rephrase the result naturally, but must not expose internal technical payloads.

## Customer and order flow

- Require a validated customer context before order creation when needed.
- Reuse the current cart, order, payment, invoice, and delivery logic already in the project.
- Do not compute tax or shipping in the AI layer; use existing PHP ecommerce business logic.
- Payment confirmation and invoice generation must use the existing webhook and payment flow.

## Code guidance for this repository

Prefer existing project files such as:

- application/controllers/api/Sk_Whatsapp_webhook.php
- application/helpers/sk_whatsapp_mcp_helper.php
- application/controllers/api/Sk_Product.php
- application/models/Sk_Product_model.php
- application/models/Sk_Product_variant_model.php
- application/controllers/api/Sk_Order.php
- application/controllers/api/Sk_Payment.php
- application/helpers/sk_whatsapp_cloud_helper.php

When implementing agent integration, add minimal missing glue code only. Avoid duplicated controllers, duplicated routes, or duplicate service layers unless a real requirement demands it.

## Response style guidance

- Natural, human-like, sales-oriented response language is the Meta Business Agent responsibility.
- MCP and PHP services must return facts; the AI agent turns them into customer-friendly replies.
- Support Tamil, English, Tanglish, and mixed-language responses depending on customer input.
- Follow the customer's style; do not force one language globally.

## Implementation expectation

For this repo, the task is not to replace the ecommerce system. The task is to add the missing AI business integration layer in the smallest safe way.

This means:

- preserve current ecommerce behavior
- reuse current product/order/payment services
- add minimal WhatsApp/TalkAI/MCP glue where missing
- enforce strict tenant scoping
- keep the AI layer conversational and fact-based
