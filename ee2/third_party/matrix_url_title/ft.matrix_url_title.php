<?php if (! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * Matrix URL Title Celltype Class for EE2
 *
 * @package   Matrix
 * @author    Brandon Kelly <brandon@pixelandtonic.com>
 * @copyright Copyright (c) 2011 Pixel & Tonic, Inc
 */
class Matrix_url_title_ft extends EE_Fieldtype {

	var $info = array(
		'name' => 'Matrix URL Title',
		'version' => '1.0.1'
	);

	var $default_settings = array(
		'title_col' => '',
		'word_separator' => '',
		'unique_titles' => '',
		'allow_numerics' => '',
		'dir' => 'ltr'
	);

	var $max_length = 75;

	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->EE = get_instance();

		// -------------------------------------------
		//  Prepare Cache
		// -------------------------------------------

		if (! isset($this->EE->session->cache['matrix_url_title']['celltypes']['text']))
		{
			$this->EE->session->cache['matrix_url_title']['celltypes']['text'] = array();
		}
		$this->cache =& $this->EE->session->cache['matrix_url_title']['celltypes']['text'];
	}

	/**
	 * Prep Settings
	 */
	private function _prep_settings(&$settings)
	{
		$settings = array_merge($this->default_settings, $settings);
	}

	// --------------------------------------------------------------------

	/**
	 * Theme URL
	 */
	private function _theme_url()
	{
		if (! isset($this->cache['theme_url']))
		{
			$theme_folder_url = defined('URL_THIRD_THEMES') ? URL_THIRD_THEMES : $this->EE->config->slash_item('theme_folder_url').'third_party/';
			$this->cache['theme_url'] = $theme_folder_url.'matrix_url_title/';
		}

		return $this->cache['theme_url'];
	}

	/**
	 * Include Theme JS
	 */
	private function _include_theme_js($file)
	{
		if (! empty($this->settings['word_separator']))
		{
			$word_separator = $this->settings['word_separator'] != "dash" ? '_' : '-';
		}
		else 
		{
			$word_separator = $this->EE->config->item('word_separator') != "dash" ? '_' : '-';
		}

		$this->EE->cp->add_to_foot('<script type="text/javascript" src="'.$this->_theme_url().$file.'"></script>');
		$this->EE->javascript->set_global(array(
				'publish.word_separator'	=> $word_separator
			));
	}

	// --------------------------------------------------------------------

	/**
	 * Display Cell Settings
	 */
	function display_cell_settings($data)
	{

		$this->_prep_settings($data);

		// load the language file
		$this->EE->lang->loadfile('matrix_url_title');
		
		$separator_select = array(
			'' => 'default',
			'dash' => 'dash',
			'underscore' => 'underscore'
		);

		return array(
			array(lang('title_col'), form_input('title_col', $data['title_col'], 'class="matrix-textarea"')),
			array(lang('unique_titles'), form_checkbox('unique_titles', 'y', (isset($data['unique_titles']) && $data['unique_titles'] == 'y'))),
			array(lang('allow_numerics'), form_checkbox('allow_numerics', 'y', (isset($data['allow_numerics']) && $data['allow_numerics'] == 'y'))),
			array(lang('word_separator'), form_dropdown('word_separator', $separator_select, $data['word_separator']))
		);
	}

	/**
	 * Modify exp_matrix_data Column Settings
	 */
	function settings_modify_matrix_column($data)
	{
		return array(
			'col_id_'.$data['col_id'] => array('type' => 'varchar', 'constraint' => $this->max_length)
		);
	}

	// --------------------------------------------------------------------

	/**
	 * Display Field
	 */
	function display_field($data)
	{
		return 'This fieldtype only works within Matrix fields.';
	}

	/**
	 * Display Cell
	 */
	function display_cell($data)
	{
		$this->_prep_settings($this->settings);

		if (! isset($this->cache['displayed']))
		{
			// include matrix_url_title.js
			$this->_include_theme_js('scripts/matrix_url_title.js');

			$this->cache['displayed'] = TRUE;
		}

		$r['class'] = 'matrix-text';
		$r['data'] = '<input type="text" class="matrix-textarea" name="'.$this->cell_name.'" maxlength="'.$this->max_length.'" dir="'.$this->settings['dir'].'" value="'.$data.'" />';

		return $r;
	}

	// --------------------------------------------------------------------

	/**
	 * Save Cell
	 */
	function save_cell($data)
	{
		$allow_numerics = isset($this->settings['allow_numerics']) && $this->settings['allow_numerics'] == 'y' ? TRUE : FALSE;
		$unique_titles = isset($this->settings['unique_titles']) && $this->settings['unique_titles'] == 'y' ? TRUE : FALSE;
		
		// ignore if empty
		if ( $data === '' ) return '';

		// ignore if empty or numeric
		if (! $allow_numerics && is_numeric($data)) return '';

		// is this a new row?
		$new = (substr($this->settings['row_name'], 0, 8) == 'row_new_');

		if (! $new)
		{
			// get the row ID
			$row_id = substr($this->settings['row_name'], 7);
		}

		// if don't need unique titles then return it
		if (! $unique_titles)
		{
			return $data;
		}
		
		// try up to 50 different URL titles
		for ($i = 0; $i < 50; $i++)
		{
			$temp = $i ? $data.$i : $data;

			$this->EE->db->select('count(row_id) AS count')
			             ->where('field_id', $this->settings['field_id'])
			             ->where($this->settings['col_name'], $temp);

			if (! $new)
			{
				// make sure to ignore this current row
				$this->EE->db->where('row_id !=', $row_id);
			}

			// get the count
			$count = $this->EE->db->get('matrix_data')->row('count');

			if (! $count) return $temp;
		}
	}

}
