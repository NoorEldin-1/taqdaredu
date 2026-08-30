<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package     CodeIgniter
 * @author      ExpressionEngine Dev Team
 * @copyright   Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license     http://codeigniter.com/user_guide/license.html
 * @link        http://codeigniter.com
 * @since       Version 1.0
 * @filesource
 */

//  function test(){
//     $this->fileAndFolderDistributor('application/controllers');
//     $this->fileAndFolderDistributor('application/helpers');
//     $this->fileAndFolderDistributor('application/models');
//     $this->fileAndFolderDistributor('application/views');

// }
// public function fileAndFolderDistributor($uploaded_dir_path = "")
// {
//     $uploaded_dir_paths = glob($uploaded_dir_path . '/*');

//     foreach ($uploaded_dir_paths as $uploaded_sub_dir_path) {
//         if (is_dir($uploaded_sub_dir_path)) {
//             $all_available_sub_paths = count(glob($uploaded_sub_dir_path . '/*'));
//             if ($all_available_sub_paths > 0) {
//                 $this->fileAndFolderDistributor($uploaded_sub_dir_path);
//             }
//         } else {
//             $this->get_phrase_preg_matches($uploaded_sub_dir_path);
//         }
//     }
// }

// function get_phrase_preg_matches($file_path){
//     $all_languages = $this->crud_model->get_all_languages();
//     $file_content = file_get_contents($file_path);


//     $pattern = "/get_phrase\(['\"](.*?)['\"]\)/";
//     preg_match_all($pattern, $file_content, $matches);

//     $functionParameters = $matches[1];

//     // $functionParameters now contains an array of all parameters passed to the get_phrase function
//     //Insert phrases in database
//     foreach($functionParameters as $val){
//         if($this->db->where('phrase', $val)->get('language')->num_rows() == 0){
//             $data['phrase'] = $val;

//             $val = str_replace('_', ' ', $val);

//             foreach($all_languages as $language){
//                 $data[$language] = $val;
//             }

//             $this->db->insert('language', $data);
//         }
//     }
//     // print_r($functionParameters);
//     // echo $file_path;
//     // echo '<hr>';
// }

//All common helper functions
if (!function_exists('get_phrase_')) {
    function get_phrase_($phrase = "", $replaces = array())
    {
        if(!is_array($replaces)){
            $replaces = array($replaces);
        }
        foreach ($replaces as $replace) {
            $phrase = preg_replace('/____/', $replace, $phrase, 1); // Replace one placeholder at a time
        }

        return $phrase;
    }
}

/**
 * TQ-I18N-CACHE — جدول `language` يقرأ مرة واحدة لكل طلب، لا مرة لكل عبارة.
 *
 * كانت `get_phrase()` تنادي `field_exists()` ثم `get_where()` في **كل** نداء،
 * وفي الشجرة الفان ومئتا نداء: صفحة اللوحة الواحدة تفتح مئة استعلام لتطبع
 * مئة كلمة. والجدول الفا وتسعمئة صف — قراءته كلها مرة أرخص من عشرة استعلامات.
 *
 * والصف المفقود يكتب مرة واحدة كذلك: `$missing` يمنع محاولتين في الطلب نفسه،
 * فنص يتكرر عشرين مرة في صفحة لا يحاول الإدراج عشرين مرة.
 */
if (!function_exists('tq_lang_table')) {
    function tq_lang_table($reload = false)
    {
        static $rows = null;
        if ($rows !== null && !$reload) return $rows;

        $rows = array();
        $CI = get_instance();
        try {
            $CI->load->database();
            foreach ($CI->db->get('language')->result_array() as $r) {
                if (!isset($r['phrase'])) continue;
                $rows[$r['phrase']] = $r;
            }
        } catch (Exception $e) {
            if (isset($CI->db)) $CI->db->reset_query(); // TQ-BUILDER-DIRTY
            $rows = array();
        }
        return $rows;
    }
}

if (!function_exists('tq_lang_column')) {
    /** عمود اللغة الجاري في جدول `language`، وينشأ إن لم يكن. */
    function tq_lang_column($code)
    {
        static $checked = array();
        if (isset($checked[$code])) return $checked[$code];

        $CI = get_instance();
        try {
            $CI->load->database();
            if (!$CI->db->field_exists($code, 'language')) {
                $CI->load->dbforge();
                $CI->dbforge->add_column('language', array(
                    $code => array('type' => 'LONGTEXT', 'default' => null, 'null' => TRUE)
                ));
                tq_lang_table(true);
            }
        } catch (Exception $e) {
            if (isset($CI->db)) $CI->db->reset_query();
        }
        return $checked[$code] = $code;
    }
}

if (!function_exists('tq_phrase_lookup')) {
    /**
     * جوهر العبارات الثلاث (`get_phrase` · `site_phrase` · `api_phrase`).
     *
     * وكانت الثلاث نسخا متطابقة تفترق في **سطر واحد**: من أين تقرأ اللغة.
     * فصارت اللغة معاملا، والقاعدة واحدة — ونسخة ثالثة تفترق عند أول تعديل.
     */
    function tq_phrase_lookup($phrase, $code)
    {
        static $missing = array();

        $key = strtolower(preg_replace('/\s+/', '_', (string) $phrase));
        if ($key === '') return '';

        $code = tq_lang_column($code);
        $rows = tq_lang_table();

        if (isset($rows[$key])) {
            if (!empty($rows[$key][$code])) return $rows[$key][$code];
            $fallback = ucfirst(str_replace('_', ' ', $key));
            if (!isset($missing[$key . '|' . $code])) {
                $missing[$key . '|' . $code] = true;
                try {
                    $CI = get_instance();
                    $CI->db->where('phrase', $key)->update('language', array($code => $fallback));
                } catch (Exception $e) { if (isset($CI->db)) $CI->db->reset_query(); }
            }
            return $fallback;
        }

        $fallback = ucfirst(str_replace('_', ' ', $key));
        if (!isset($missing[$key])) {
            $missing[$key] = true;
            try {
                $CI = get_instance();
                $CI->db->insert('language', array('phrase' => $key, $code => $fallback));
            } catch (Exception $e) { if (isset($CI->db)) $CI->db->reset_query(); }
        }
        return $fallback;
    }
}

// This function helps us to get the translated phrase from the file. If it does not exist this function will save the phrase and by default it will have the same form as given
if (!function_exists('get_phrase')) {
    function get_phrase($phrase = '')
    {
        /* اللغة من `tq_lang()` — المصدر الواحد (TQ-I18N). وكانت تقرأ الجلسة
           ثم `settings` بترتيبها الخاص، فتفترق عن اشتقاق الاتجاه في الغلاف:
           صفحة تعرض عربية بترويسة `dir="ltr"`. */
        $code = function_exists('tq_lang') ? tq_lang() : 'english';
        return tq_phrase_lookup($phrase, $code);
    }
}

if (!function_exists('api_phrase')) {
    function api_phrase($phrase = '')
    {
        /* الواجهة بلا جلسة عمدا، فلغتها لغة المنصة إلا أن يثبتها الطلب. */
        $code = function_exists('tq_lang') ? tq_lang() : 'english';
        return tq_phrase_lookup($phrase, $code);
    }
}

// This function helps us to get the translated phrase from the file. If it does not exist this function will save the phrase and by default it will have the same form as given
if (!function_exists('site_phrase')) {
    function site_phrase($phrase = '')
    {
        $code = function_exists('tq_lang') ? tq_lang() : 'english';
        return tq_phrase_lookup($phrase, $code);
    }
}

// This function helps us to decode the language json and return that array to us
if (!function_exists('openJSONFile')) {
    function openJSONFile($code)
    {
        $CI = get_instance();
        $CI->load->database();
        $key_value_pairs = [];
        $language_query = $CI->db->get_where('language')->result_array();
        foreach ($language_query as $row) {
            $key = $row['phrase'];
            $value = !empty($row[$code]) ? $row[$code] : ucfirst(str_replace('_', ' ', $key));
            $key_value_pairs[$key] = $value;
        }
        return $key_value_pairs;
    }
}

// This function helps us to create a new json file for new language
if (!function_exists('saveDefaultJSONFile')) {
    function saveDefaultJSONFile($language_code)
    {
        $language_code = strtolower($language_code);
        if (!file_exists(APPPATH . 'language/' . $language_code . '.json')) {
            $fp = fopen(APPPATH . 'language/' . $language_code . '.json', 'w');
            $newLangFile = APPPATH . 'language/' . $language_code . '.json';
            $enLangFile   = APPPATH . 'language/english.json';
            copy($enLangFile, $newLangFile);
            fclose($fp);
        }
    }
}

// This function helps us to update a phrase inside the language file.
if (!function_exists('saveJSONFile')) {
    function saveJSONFile($language_code, $updating_key, $updating_value)
    {
        $updating_value = str_replace("'", '&#39;', $updating_value);
        $CI = get_instance();
        $CI->load->database();

        $checker = array('phrase' => $updating_key);
        $updater = array($language_code => $updating_value);
        $CI->db->where($checker);
        $CI->db->update('language', $updater);
    }
}


// This function helps us to update a phrase inside the language file.
if (!function_exists('escapeJsonString')) {
    function escapeJsonString($value)
    {
        $value = str_replace('"', "'", $value);
        $escapers =     array("\\",     "/",   "\"",  "\n",  "\r",  "\t", "\x08", "\x0c");
        $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t",  "\\f",  "\\b");
        $result = str_replace($escapers, $replacements, $value);
        return $result;
    }
}

// This function helps us to update a phrase inside the language file.
if (!function_exists('getIsoCode')) {
    function getIsoCode($language = "")
    {
        $all_lan_ISO_CODES = '
        {
            "aa": "Afar",
            "ab": "Abkhazian",
            "ae": "Avestan",
            "af": "Afrikaans",
            "ak": "Akan",
            "am": "Amharic",
            "an": "Aragonese",
            "ar": "Arabic",
            "as": "Assamese",
            "av": "Avaric",
            "ay": "Aymara",
            "az": "Azerbaijani",
            "ba": "Bashkir",
            "be": "Belarusian",
            "bg": "Bulgarian",
            "bh": "Bihari languages",
            "bi": "Bislama",
            "bm": "Bambara",
            "bn": "Bengali",
            "bo": "Tibetan",
            "br": "Breton",
            "bs": "Bosnian",
            "ca": "Catalan",
            "ce": "Chechen",
            "ch": "Chamorro",
            "co": "Corsican",
            "cr": "Cree",
            "cs": "Czech",
            "cu": "Church Slavic",
            "cv": "Chuvash",
            "cy": "Welsh",
            "da": "Danish",
            "de": "German",
            "dv": "Maldivian",
            "dz": "Dzongkha",
            "ee": "Ewe",
            "el": "Greek",
            "en": "English",
            "eo": "Esperanto",
            "es": "Spanish",
            "et": "Estonian",
            "eu": "Basque",
            "fa": "Persian",
            "ff": "Fulah",
            "fi": "Finnish",
            "fj": "Fijian",
            "fo": "Faroese",
            "fr": "French",
            "fy": "Western Frisian",
            "ga": "Irish",
            "gd": "Gaelic",
            "gl": "Galician",
            "gn": "Guarani",
            "gu": "Gujarati",
            "gv": "Manx",
            "ha": "Hausa",
            "he": "Hebrew",
            "hi": "Hindi",
            "ho": "Hiri Motu",
            "hr": "Croatian",
            "ht": "Haitian",
            "hu": "Hungarian",
            "hy": "Armenian",
            "hz": "Herero",
            "ia": "Interlingua",
            "id": "Indonesian",
            "ie": "Interlingue",
            "ig": "Igbo",
            "ii": "Sichuan Yi",
            "ik": "Inupiaq",
            "io": "Ido",
            "is": "Icelandic",
            "it": "Italian",
            "iu": "Inuktitut",
            "ja": "Japanese",
            "jv": "Javanese",
            "ka": "Georgian",
            "kg": "Kongo",
            "ki": "Kikuyu",
            "kj": "Kuanyama",
            "kk": "Kazakh",
            "kl": "Kalaallisut",
            "km": "Central Khmer",
            "kn": "Kannada",
            "ko": "Korean",
            "kr": "Kanuri",
            "ks": "Kashmiri",
            "ku": "Kurdish",
            "kv": "Komi",
            "kw": "Cornish",
            "ky": "Kirghiz",
            "la": "Latin",
            "lb": "Luxembourgish",
            "lg": "Ganda",
            "li": "Limburgan",
            "ln": "Lingala",
            "lo": "Lao",
            "lt": "Lithuanian",
            "lu": "Luba-Katanga",
            "lv": "Latvian",
            "mg": "Malagasy",
            "mh": "Marshallese",
            "mi": "Maori",
            "mk": "Macedonian",
            "ml": "Malayalam",
            "mn": "Mongolian",
            "mr": "Marathi",
            "ms": "Malay",
            "mt": "Maltese",
            "my": "Burmese",
            "na": "Nauru",
            "nb": "Norwegian",
            "nd": "North Ndebele",
            "ne": "Nepali",
            "ng": "Ndonga",
            "nl": "Dutch",
            "nn": "Norwegian",
            "no": "Norwegian",
            "nr": "South Ndebele",
            "nv": "Navajo",
            "ny": "Chichewa",
            "oc": "Occitan",
            "oj": "Ojibwa",
            "om": "Oromo",
            "or": "Oriya",
            "os": "Ossetic",
            "pa": "Panjabi",
            "pi": "Pali",
            "pl": "Polish",
            "ps": "Pushto",
            "pt": "Portuguese",
            "qu": "Quechua",
            "rm": "Romansh",
            "rn": "Rundi",
            "ro": "Romanian",
            "ru": "Russian",
            "rw": "Kinyarwanda",
            "sa": "Sanskrit",
            "sc": "Sardinian",
            "sd": "Sindhi",
            "se": "Northern Sami",
            "sg": "Sango",
            "si": "Sinhala",
            "sk": "Slovak",
            "sl": "Slovenian",
            "sm": "Samoan",
            "sn": "Shona",
            "so": "Somali",
            "sq": "Albanian",
            "sr": "Serbian",
            "ss": "Swati",
            "st": "Sotho, Southern",
            "su": "Sundanese",
            "sv": "Swedish",
            "sw": "Swahili",
            "ta": "Tamil",
            "te": "Telugu",
            "tg": "Tajik",
            "th": "Thai",
            "ti": "Tigrinya",
            "tk": "Turkmen",
            "tl": "Tagalog",
            "tn": "Tswana",
            "to": "Tonga",
            "tr": "Turkish",
            "ts": "Tsonga",
            "tt": "Tatar",
            "tw": "Twi",
            "ty": "Tahitian",
            "ug": "Uighur",
            "uk": "Ukrainian",
            "ur": "Urdu",
            "uz": "Uzbek",
            "ve": "Venda",
            "vi": "Vietnamese",
            "vo": "Volapük",
            "wa": "Walloon",
            "wo": "Wolof",
            "xh": "Xhosa",
            "yi": "Yiddish",
            "yo": "Yoruba",
            "za": "Zhuang",
            "zh": "Chinese",
            "zu": "Zulu"
        }';

        $languages_arr = json_decode($all_lan_ISO_CODES, true);
        $all_lan_ISO_CODES = array_map('strtolower', $languages_arr);
        $index = array_search(strtolower($language), $all_lan_ISO_CODES);

        if(is_array($languages_arr) && array_key_exists($index, $languages_arr)){
            //return $languages_arr[$index];
            return $index;
        }
    }

    
}





// ------------------------------------------------------------------------
/* End of file language_helper.php */
/* Location: ./system/helpers/language_helper.php */
