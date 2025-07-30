<?php

add_action('admin_init', 'aev_register_settings');

function aev_register_settings() {
    $schema = aev_get_settings_schema();
    foreach ($schema as $key => $args) {
        register_setting('aev_settings_group', $key, $args);
        add_settings_field(
            $key,
            $args['label'],
            'aev_generic_field_callback',
            'aev-settings',
            'aev_main_section',
            array_merge($args, ['id' => $key])
        );
    }
    add_settings_section(
        'aev_main_section', 
        'Main Settings', 
        null, 
        'aev-settings', [
            'before_section'=> '<a href="options-general.php?page=aev-email-history">View Email Validation History</a>',
            'after_section' => '',
            'section_class' => 'aev-section'

        ]
    );
}