<?php

/**
 * Event Handlers
 * 
 * Each handler captures relevant data and sends it to OpenKBS via the openkbs_publish function.
 * Handlers provide detailed information about the event that will be processed by the AI Agent.
 * 
 * openkbs_publish($event, $title) expects:
 *   - $event: { event: wp_action_name, ...props }
 *   - $title: Chat display title for this event instance
 */

require_once plugin_dir_path(__FILE__) . 'openkbs-utils.php';

// Register Contact Form 7 hooks
function hook_wpcf7_events() {
    add_action('wpcf7_mail_sent', 'handle_cf7_mail_sent', 10, 1);
    add_action('wpcf7_mail_failed', 'handle_cf7_mail_failed', 10, 1);
}

// Handler for successful form submissions
function handle_cf7_mail_sent($contact_form) {
    process_cf7_submission($contact_form, true);
}

// Handler for failed form submissions
function handle_cf7_mail_failed($contact_form) {
    process_cf7_submission($contact_form, false);
}

// Core processing function for both success and failure cases
function process_cf7_submission($contact_form, $is_success) {
    $submission = WPCF7_Submission::get_instance();
    
    if (!$submission) return;

    $event_type = $is_success ? 'wpcf7_mail_sent' : 'wpcf7_mail_failed';

    // Build base content structure
    $event = array(
        'event' => $event_type,
        'form' => get_form_data($contact_form),
        'submitted_data' => get_submission_data($submission),
        'mail' => get_mail_data($contact_form),
        'timestamp' => current_time('Y-m-d H:i:s')
    );

    // Add error data for failed submissions
    if (!$is_success) {
        $event['error_data'] = array(
            'invalid_fields' => $submission->get_invalid_fields(),
            'validation_errors' => $contact_form->get_properties()['messages']
        );
    }

    // Get form settings
    $form_settings = $contact_form->prop('additional_settings');
    if (!empty($form_settings)) {
        $event['form']['additional_settings'] = $form_settings;
    }

    sanitize_sensitive_data($event);
    
    $event_prefix = $is_success ? 'cf7-mail-sent: ' : 'cf7-mail-failed: ';
    openkbs_publish($event, $event_prefix . $contact_form->id());
}

// Helper functions
function get_form_data($contact_form) {
    return array(
        'id' => $contact_form->id(),
        'name' => $contact_form->name(),
        'title' => $contact_form->title(),
        'locale' => $contact_form->locale()
    );
}

function get_submission_data($submission) {
    return array(
        'posted_data' => $submission->get_posted_data(),
        'uploaded_files' => $submission->uploaded_files(),
        'timestamp' => $submission->get_meta('timestamp'),
        'remote_ip' => $submission->get_meta('remote_ip'),
        'user_agent' => $submission->get_meta('user_agent'),
        'url' => $submission->get_meta('url')
    );
}

function get_mail_data($contact_form) {
    return array(
        'template' => $contact_form->prop('mail'),
        'mail_2_template' => $contact_form->prop('mail_2'),
        'messages' => array(
            'mail_sent_ok' => $contact_form->message('mail_sent_ok'),
            'mail_sent_ng' => $contact_form->message('mail_sent_ng')
        )
    );
}

function sanitize_sensitive_data(&$event) {
    if (isset($event['submitted_data']['posted_data'])) {
        foreach ($event['submitted_data']['posted_data'] as $key => $value) {
            if (strpos(strtolower($key), 'password') !== false) {
                $event['submitted_data']['posted_data'][$key] = '[REDACTED]';
            }
        }
    }
}