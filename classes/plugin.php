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
 * @copyright 2024 Alex Orlov <snickser@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Player that creates rutube embedding.
 *
 * @package media_rutube
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_rutube_plugin extends core_media_player_external {
    /**
     *
     * @param array $urls
     * @param array $options
     */
    public function list_supported_urls(array $urls, array $options = []) {
        // These only work with a SINGLE url (there is no fallback).
        if (count($urls) == 1) {
            $url = reset($urls);

            // Check against regex.
            if (preg_match($this->get_regex(), $url->out(false), $this->matches)) {
                $this->isplaylist = false;
                return [$url];
            }
        }

        return [];
    }

    /**
     *
     * @param moodle_url $url
     * @param string $name
     * @param int $width
     * @param int $height
     * @param array $options
     */
    protected function embed_external(moodle_url $url, $name, $width, $height, $options) {
        global $OUTPUT;

        $info = trim($name ?? '');
        if (empty($info) || strpos($info, 'http') === 0) {
            $info = get_string('pluginname', 'media_rutube');
        }
        $info = s($info);

        self::pick_video_size($width, $height);

        // Template context.
        $context = [
                'width' => $width,
                'height' => $height,
                'title' => $info,
        ];

        $videoid = end($this->matches);
        $params = [];

        $start = self::get_start_time($url);
        if ($start > 0) {
            $params['t'] = $start;
        }

        $embedurl = new moodle_url('https://rutube.ru/play/embed/' . $videoid, $params);
        $context['embedurl'] = $embedurl->out(false);

        // Return the rendered template.
        return $OUTPUT->render_from_template('media_rutube/embed', $context);
    }

    /**
     * Check for start time parameter.  Note that it's in hours/mins/secs in the URL,
     * but the embedded player takes only a number of seconds as the "t" parameter.
     *
     * @param  moodle_url $url URL of video to be embedded.
     * @return int Number of seconds video should start at.
     */
    protected static function get_start_time($url) {
        $matches = [];
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
     *
     * @return string PHP regular expression e.g. '~^https?://example.org/~'
     */
    protected function get_regex() {
        // Regex for standard rutube link.
        $link = '(rutube\.ru/)';
        // Initial part of link.
        $start = '~^https?://((www|m)\.)?(' . $link . ')';
        // Last bit: Video key value.
        $middle = 'video/(private/)?(.*)';
        return $start . $middle . core_media_player_external::END_LINK_REGEX_PART;
    }

    /**
     *
     */
    public function get_embeddable_markers() {
        return ['rutube.ru'];
    }

    /**
     * Default rank
     *
     * @return int
     */
    public function get_rank() {
        return 1201;
    }
}
