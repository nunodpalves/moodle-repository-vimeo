<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This plugin is used to access vimeo videos
 *
 * @since 2.0
 * @package    repository_vimeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * repository_vimeo class
 *
 * @since 2.0
 * @package    repository_vimeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class repository_vimeo extends repository {
    const API_ENDPOINT = 'http://vimeo.com/api/v2/';
    const VIMEO_THUMBS_PER_PAGE = 27;
    const THUMBNAIL_WIDTH = 100;
    CONST THUMBNAIL_HEIGHT = 75;

    /**
     * Youtube plugin constructor
     * @param int $repositoryid
     * @param object $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);
    }

    public function check_login() {
        return !empty($this->keyword);
    }

    /**
     * Generate search form
     */
    public function print_login($ajax = true) {
        $search = new stdClass();
        $search->type = 'text';
        $search->id   = 'youtube_search';
        $search->name = 's';
        $search->label = get_string('search', 'repository_youtube') . ': ';

        $ret = array();
        $ret['login'] = array($search);
        $ret['login_btn_label'] = get_string('search');
        $ret['login_btn_action'] = 'search';
        $ret['allowcaching'] = true; // indicates that login form can be cached in filepicker.js

        return $ret;
    }

    /**
     * Return search results
     * @param string $search_text
     * @return array
     */
    public function search($search_text, $page = 0) {
        global $SESSION;

        $sort = optional_param('youtube_sort', '', PARAM_TEXT);
        $sess_keyword = 'vimeo_' . $this->id . '_keyword';

        // This is the request of another page for the last search, retrieve the cached keyword and sort
        if ($page && !$search_text && isset($SESSION->{$sess_keyword})) {
            $search_text = $SESSION->{$sess_keyword};
        }

        // Save this search in session
        $SESSION->{$sess_keyword} = $search_text;

        $this->keyword = $search_text;

        $page = (int) $page;
        if ($page < 1) {
            $page = 1;
        }

        list($list, $lastpage) = $this->_get_collection($search_text, $page);

        $ret  = array();
        $ret['nologin'] = true;
        $ret['page'] =  $page;
        $ret['list'] = $list;
        $ret['norefresh'] = true;
        $ret['nosearch'] = true;
        $ret['pages'] = $lastpage ? $page : $page + 1;

        return $ret;
    }

    private function _new_curl() {
        return new curl(array(
            'cache' => true, 
            'module_cache' => 'repository',
            'CURLOPT_RETURNTRANSFER' => 1,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_FOLLOWLOCATION' => 1
        ));
    }

    /**
     * Private method to get a vimeo user videos
     * @param string $vimeouser
     * @return array
     */
    private function _get_collection($vimeouser, $page) {
        $list = array();

        $this->feed_url = self::API_ENDPOINT . $vimeouser . '/videos.xml';

        $content = $this->_new_curl()->get($this->feed_url);
        $videos = simplexml_load_string($content)->video;

        $total = count($videos);
        $start = ($page - 1) * self::VIMEO_THUMBS_PER_PAGE;
        $end = min($page * self::VIMEO_THUMBS_PER_PAGE, $total);
        for ($i = $start; $i < $end; $i++) {
            $video = $videos[$i];
            $title = (string) $video->title;
            $description = (string) $video->description;
            if (empty($description)) {
                $description = $title;
            }
            $thumbnail = (string) $video->thumbnail_small;
            $source = (string) $video->url;
            $list[] = array(
                'shorttitle' => $title,
                'thumbnail_title' => $description,
                'title' => $title.'.avi', // this is a hack so we accept this file by extension
                'thumbnail' => $thumbnail,
                'thumbnail_width' => self::THUMBNAIL_WIDTH,
                'thumbnail_height' => self::THUMBNAIL_HEIGHT,
                'size' => '',
                'date' => '',
                'source' => $source
            );
        }

        return array($list, $total == $end);
    }

    /**
     * Youtube plugin doesn't support global search
     */
    public function global_search() {
        return false;
    }

    public function get_listing($path='', $page = '') {
        return array();
    }

    /**
     * file types supported by youtube plugin
     * @return array
     */
    public function supported_filetypes() {
        return array('video');
    }

    /**
     * Youtube plugin only return external links
     * @return int
     */
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }

    /**
     * Is this repository accessing private data?
     *
     * @return bool
     */
    public function contains_private_data() {
        return false;
    }
}
