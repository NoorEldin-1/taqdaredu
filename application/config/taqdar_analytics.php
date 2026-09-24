<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Owner confirmed these exact existing invoices were tests on 2026-09-24.
// Do not use a rolling ID/date cutoff: later customer orders must remain eligible.
$config['tq_analytics_test_invoices'] = array(37,58,85,96,98,99,100,101,102,103,104,105,106,107,108,109,110,111,112,113,114,115);

// Add only explicitly identified QA accounts here BEFORE testing the live gateway.
// Sandbox payment attempts are excluded independently. Never infer tests from price.
$config['tq_analytics_test_users'] = array();
// Owner-designated QA email, SHA-256 of lowercase trimmed email (account may be created later).
$config['tq_analytics_test_email_hashes'] = array('5165a28e063218427fa97b38c22203139ba4389b5850dab7afddcc7f8fff60cd');
