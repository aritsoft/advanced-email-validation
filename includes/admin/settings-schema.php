<?php

function aev_get_settings_schema() {
    return [
        'aev_neutrino_api_user_id' => [
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'label'             => 'Neutrino API User Id',
            'description'       => 'Enter your neutrino api user id',
            'field_type'        => 'text',
        ],
        'aev_neutrino_api_key' => [
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'label'             => 'Neutrino API User Key',
            'description'       => 'Enter your neutrino api key',
            'field_type'        => 'text',
        ],
        'aev_free_email_limit' => [
            'type'              => 'integer',
            'default'           => 50,
            'sanitize_callback' => 'absint',
            'label'             => 'Free Email Limit',
            'description'       => 'Number of free emails allowed.',
            'field_type'        => 'number',
        ],
        'aev_disposable_domains' => [
            'type'              => 'string',
            'rows'              => 10,
            'cols'              => 75,
            'default'           => "",
            'sanitize_callback' => 'sanitize_text_field',
            'label'             => 'Disposable Domains',
            'description'       => 'Enter disposable domains per line',
            'field_type'        => 'textarea',
        ],
        'aev_email_providers' => [
            'type'              => 'string',
            'rows'              => 10,
            'cols'              => 75,
            'default'           => "gmail.com, yahoo.com, hotmail.com, outlook.com, icloud.com",
            'sanitize_callback' => 'sanitize_text_field',
            'label'             => 'Major Provider',
            'description'       => 'Enter major email providers to skip',
            'field_type'        => 'textarea',
        ],
        'aev_enable_debugging' => [
            'type'              => 'boolean',
            'default'           => 0,
            'sanitize_callback' => 'rest_sanitize_boolean',
            'label'             => 'Enable Debugging',
            'description'       => 'Check to enable debugging.',
            'field_type'        => 'checkbox',
        ],
    ];
}
