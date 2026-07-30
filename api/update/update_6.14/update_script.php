<?php
$CI = get_instance();
$CI->load->database();
$CI->load->dbforge();





// CREATING badges TABLE
$badges = array(
    'id' => array(
        'type' => 'INT',
        'constraint' => 11,
        'unsigned' => TRUE,
        'auto_increment' => TRUE,
        'collation' => 'utf8_unicode_ci'
    ),
    'type' => array(
        'type' => 'varchar',
        'constraint' => 255,
        'null' => TRUE,
        'collation' => 'utf8_unicode_ci'
    ),
    'title' => array(
        'type' => 'varchar',
        'constraint' => 255,
        'null' => TRUE,
        'collation' => 'utf8_unicode_ci'
    ),
    'image' => array(
        'type' => 'varchar',
        'constraint' => 255,
        'null' => TRUE,
        'collation' => 'utf8_unicode_ci'
    ),
    'condition_from' => array(
        'type' => 'INT',
        'constraint' => 11,
        'null' => TRUE,
        'collation' => 'utf8_unicode_ci'
    ),
    'condition_to' => array(
        'type' => 'INT',
        'constraint' => 11,
        'null' => TRUE,
        'collation' => 'utf8_unicode_ci'
    ),
    'description' => array(
        'type' => 'varchar',
        'constraint' => 255,
        'null' => TRUE,
        'collation' => 'utf8_unicode_ci'
    ),
    'created_at' => array(
        'type' => 'varchar',
        'constraint' => 100,
        'collation' => 'utf8_unicode_ci'
    ),
    'updated_at' => array(
        'type' => 'varchar',
        'constraint' => 100,
        'collation' => 'utf8_unicode_ci'
    )
);
$CI->dbforge->add_field($badges);
$CI->dbforge->add_key('id', TRUE);
$attributes = array('collation' => "utf8_unicode_ci");
$CI->dbforge->create_table('badges', TRUE);















//Water Mark  
if($CI->db->get_where('frontend_settings', ['key' => 'review_section'])->num_rows() == 0){
	$CI->db->insert('frontend_settings', ['key' => 'review_section', 'value' => 1]);
}
if($CI->db->get_where('frontend_settings', ['key' => 'water_mark_speed'])->num_rows() == 0){
	$CI->db->insert('frontend_settings', ['key' => 'water_mark_speed', 'value' => 2000]);
}
if($CI->db->get_where('frontend_settings', ['key' => 'water_mark_opacity'])->num_rows() == 0){
	$CI->db->insert('frontend_settings', ['key' => 'water_mark_opacity', 'value' => 1]);
}
if($CI->db->get_where('frontend_settings', ['key' => 'water_mark_color'])->num_rows() == 0){
	$CI->db->insert('frontend_settings', ['key' => 'water_mark_color', 'value' => '#fff']);
}




// UPDATE VERSION NUMBER INSIDE SETTINGS TABLE
$settings_data = array('value' => '6.14');
$CI->db->where('key', 'version');
$CI->db->update('settings', $settings_data);
