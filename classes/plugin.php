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
 * Main class for plugin 'media_rutube'
 *
 * @package   media_rutube
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Player that creates rutube embedding.
 *
 * @package   media_rutube
 * @author    2011 The Open University
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_rutube_plugin extends core_media_player_external {
    /**
     * Stores whether the playlist regex was matched last time when
     * {@link list_supported_urls()} was called
     * @var bool
     */
    protected $isplaylist = false;

    public function list_supported_urls(array $urls, array $options = array()) {
        // These only work with a SINGLE url (there is no fallback).
        if (count($urls) == 1) {
            $url = reset($urls);

            // Check against regex.
            if (preg_match($this->get_regex(), $url->out(false), $this->matches)) {

//echo serialize($this->matches)."<br>\n";

                $this->isplaylist = false;
                return array($url);
            }

            // Check against playlist regex.
            if (preg_match($this->get_regex_playlist(), $url->out(false), $this->matches)) {
                $this->isplaylist = true;
                return array($url);
            }
        }

        return array();
    }

    protected function embed_external(moodle_url $url, $name, $width, $height, $options) {
        global $OUTPUT;
        $nocookie = get_config('media_rutube', 'nocookie');

        $info = trim($name ?? '');
        if (empty($info) or strpos($info, 'http') === 0) {
            $info = get_string('pluginname', 'media_rutube');
        }
        $info = s($info);

        self::pick_video_size($width, $height);

        // Template context.
        $context = [
                'width' => $width,
                'height' => $height
        ];

        if ($this->isplaylist) {
            $site = $this->matches[1];
            $playlist = $this->matches[3];

            $params = ['list' => $playlist];

            // Handle no cookie option.
            if (!$nocookie) {
                $embedurl = new moodle_url("https://$site/embed/videoseries", $params);
            } else {
                $embedurl = new moodle_url('https://www.rutube-nocookie.com/embed/videoseries', $params );
            }
            $context['embedurl'] = $embedurl->out(false);

            // Return the rendered template.
            return $OUTPUT->render_from_template('media_rutube/embed', $context);

        } else {
            $videoid = end($this->matches);
            $params = [];
            $start = self::get_start_time($url);
            if ($start > 0) {
                $params['start'] = $start;
            }

            $listid = $url->param('list');
            // Check for non-empty but valid playlist ID.
            if (!empty($listid) && !preg_match('/[^a-zA-Z0-9\-_]/', $listid)) {
                // This video is part of a playlist, and we want to embed it as such.
                $params['list'] = $listid;
            }

            // Add parameters to object to be passed to the mustache template.
//            $params['rel'] = 0;
//            $params['wmode'] = 'transparent';

            // Handle no cookie option.
            if (!$nocookie) {
                $embedurl = new moodle_url('https://rutube.ru/play/embed/' . $videoid, $params );
            } else {
                $embedurl = new moodle_url('https://www.rutube-nocookie.com/embed/' . $videoid, $params );
            }

            $context['embedurl'] = $embedurl->out(false);

            // Return the rendered template.
            return $OUTPUT->render_from_template('media_rutube/embed', $context);
        }

    }

    /**
     * Check for start time parameter.  Note that it's in hours/mins/secs in the URL,
     * but the embedded player takes only a number of seconds as the "start" parameter.
     * @param moodle_url $url URL of video to be embedded.
     * @return int Number of seconds video should start at.
     */
    protected static function get_start_time($url) {
        $matches = array();
        $seconds = 0;

        $rawtime = $url->param('t');
        if (empty($rawtime)) {
            $rawtime = $url->param('start');
        }

        if (is_numeric($rawtime)) {
            // Start time already specified as a number of seconds; ensure it's an integer.
            $seconds = $rawtime;
        } else if (preg_match('/(\d+?h)?(\d+?m)?(\d+?s)?/i', $rawtime ?? '', $matches)) {
            // Convert into a raw number of seconds, as that's all embedded players accept.
            for ($i = 1; $i < count($matches); $i++) {
                if (empty($matches[$i])) {
                    continue;
                }
                $part = str_split($matches[$i], strlen($matches[$i]) - 1);
                switch ($part[1]) {
                    case 'h':
                        $seconds += 3600 * $part[0];
                        break;
                    case 'm':
                        $seconds += 60 * $part[0];
                        break;
                    default:
                        $seconds += $part[0];
                }
            }
        }

        return intval($seconds);
    }

    /**
     * Returns regular expression used to match URLs for single rutube video
     * @return string PHP regular expression e.g. '~^https?://example.org/~'
     */
    protected function get_regex() {
        // Regex for standard rutube link.
        $link = '(rutube\.ru/)';

        // Initial part of link.
        $start = '~^https?://((www|m)\.)?(' . $link . ')';
        // Middle bit: Video key value.
        $middle = 'video/(private/)?(.*)';
        return $start . $middle . core_media_player_external::END_LINK_REGEX_PART;
    }

    /**
     * Returns regular expression used to match URLs for rutube playlist
     * @return string PHP regular expression e.g. '~^https?://example.org/~'
     */
    protected function get_regex_playlist() {
        // Initial part of link.
        $start = '~^https?://(rutube)?\.ru)/';
        // Middle bit: either view_play_list?p= or p/ (doesn't work on rutube) or playlist?list=.
        $middle = '(?:view_play_list\?p=|p/|playlist\?list=)([a-z0-9\-_]+)';
        return $start . $middle . core_media_player_external::END_LINK_REGEX_PART;
    }

    public function get_embeddable_markers() {
        return array('rutube.ru');
    }

    /**
     * Default rank
     * @return int
     */
    public function get_rank() {
        return 1201;
    }
}
