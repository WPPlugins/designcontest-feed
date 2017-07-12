<?php
/*

Plugin name: DesignContest Feed
Plugin URI: http://wordpress.org/extend/plugins/designcontest-feed/
Description: DesignContest Feed - display active contests feed from www.designcontest.com at your website in 2 clicks.
Version: 0.1
Author: www.designcontest.com
Author URI: http://www.designcontest.com/

Copyright 2015  www.designcontest.com

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

class DCRSS extends WP_Widget
{

    function DCRSS()
    {
        $widget_ops = array('classname' => 'DCRSS', 'description' => __('Recent contests from DesignContest.com', 'DCRSS'));
        $control_ops = array('width' => 400);
        $this->WP_Widget('DCRSS', 'DesignContest Feed', $widget_ops, $control_ops);
    }


	
	
	function widget($args, $instance) {
		extract($args);
		$title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title']);
		$max_items = intval($instance['max_items']);
		$max_per_feed = intval($instance['max_per_feed']);
		$cachetime = intval($instance['cachetime']);
		$display_style = $instance['style'];
		$show_date = intval($instance['show_date']);
        $show_prize = intval($instance['show_prize']);

		$date_format = $instance['date_format'];		
		$url = explode("\n",$instance['feeds']);
		
		// Build it
		$items = array();


		foreach($url as $rss_url){
			$rss_url = trim($rss_url);
			if($rss_url!='' AND ($display_style=='time' OR count($items)<$max_items)){
				$feed = $this->fetch_feed($rss_url,$cachetime);
				if(is_wp_error($feed)) {
					// report the error?
				} else {
					$feedname = $feed->get_title();
					$feedlink = esc_url(strip_tags($feed->get_permalink()));
					while ( stristr($feedlink, 'http') != $feedlink ) {
						$feedlink = substr($link, 1);
					}



					// $counter=0;
					foreach ($feed->get_items(0, $max_per_feed) as $item) {

                        $prize = '';
                        if(isset($item->get_item_tags('', 'prize')[0]['data']))
                        {
                            $prize = '$'.$item->get_item_tags('', 'prize')[0]['data'];
                        }

                        $guaranteed = false;

                        if(isset($item->get_item_tags('', 'guaranteed')[0]['data']))
                        {
                            if ((int)$item->get_item_tags('', 'guaranteed')[0]['data'] !==0)
                                $guaranteed = true;
                        }


                        $itemdata = array();
						$itemdata['id'] = $item->get_id();
						$itemdata['feedname'] = $feedname;
						$itemdata['feedlink'] = $feedlink;
						$itemdata['title'] = $item->get_title();
						$itemdata['timestamp'] = strtotime($item->get_date());
						$itemdata['link'] = $item->get_permalink();
						$itemdata['description'] = str_replace(array("\n", "\r"), ' ', esc_attr(strip_tags(@html_entity_decode($item->get_description(), ENT_QUOTES, get_option('blog_charset')))));;
						$itemdata['description'] =  wp_html_excerpt( $itemdata['description'], 200 ) . '&hellip; ';
						$itemdata['description'] = esc_html( $itemdata['description'] );
						$itemdata['prize'] = $prize;
                        $itemdata['guaranteed'] = $guaranteed;
						

						
						if($display_style == 'time') {
							$key = $itemdata['timestamp'];
							while(isset($items[$key])) { // I know, it's a kludge...
								$key++;
							}
						} else {
							$key = $itemdata['id'];
						}
						
						$items[$key] = $itemdata;
					} // end foreach $item
				}
			}
		} // end foreach $url
		
		

		if($display_style == 'time') { krsort($items); }
		

		$output = '';
		$total_items = 0;
		$feed_items = array();

		foreach($items as $item) {

			if($total_items < $max_items){

				if(!isset($feed_items[$item['feedname']])){
					$feed_items[$item['feedname']]=1;
					if($display_style=='feed'){
						if($total_items){
							$output .= '</div>';
						}
						$output .= '<div class="DCRSS-feedtitle"><a href="'.$item['feedlink'].'">'.$item['feedname'].'</a></div><div>';
					} elseif($output=='') {
						$output .= '<div>';
					}
				} else {
					$feed_items[$item['feedname']]++;
				}

				$link_title = $item['title'].' '.$item['description'];

				if($show_date) {
					$item_date = date_i18n($date_format,$item['timestamp']);
					if(trim($item_date)!='') {
						$link_title .= ' '.$item_date;
					}
				}

                if($show_prize) {
                    $item_prize = $item['prize'];

                }



				$output .= '<div class="DCRSS-item"><a class="DCRSS-item" href="'.$item['link'].'" title="'.$link_title.'" target="_blank">'.$item['title'].'</a>';

                if($show_prize) { $output .= ' <span class="DCRSS-prize '.(($item['guaranteed'])?'DCRSS-prize-green':'').'">'.$item_prize.'</span>'; }

				if($show_date) { $output .= ' <span class="DCRSS-date">'.$item_date.'</span>'; }



				$output .= '</div>';
				$total_items++;
			}

		}	// end foreach

		$output .= '</ul>';


        wp_enqueue_style( 'dcrss-widget-style', plugins_url('dcstyle.css', __FILE__) );

		// output
		echo $before_widget;
		if($title)
			echo $before_title.$title.$after_title;
		echo $output;
		echo $after_widget;
	
	}
	
	
	// lifted from wp-includes/feed.php so that we could have flexible caching
	function fetch_feed($url, $cache_seconds=43200) {
		require_once (ABSPATH . WPINC . '/class-feed.php');

		$feed = new SimplePie();
		$feed->set_feed_url($url);
		$feed->set_cache_class('WP_Feed_Cache');
		$feed->set_file_class('WP_SimplePie_File');
		$feed->set_cache_duration(apply_filters('wp_feed_cache_transient_lifetime', $cache_seconds));
		$feed->init();
		$feed->handle_content_type();

		if ( $feed->error() ) {
			return new WP_Error('simplepie-error', $feed->error());
		}
		
		return $feed;
	}
	
	
	function update($new_instance, $old_instance) {
		if(!isset($new_instance['show_date'])) { $new_instance['show_date'] = 0; }
        if(!isset($new_instance['show_prize'])) { $new_instance['show_prize'] = 1; }
        if(!isset($new_instance['feeds'])) { $new_instance['feeds'] = "https://www.designcontest.com/rss.html"; }
	
		$instance = $old_instance;
		$instance['title'] = strip_tags(stripslashes($new_instance['title']));
        $instance['feeds'] = strip_tags(stripslashes($new_instance['feeds']));
		$instance['style'] = $new_instance['style'];
		$instance['max_items'] = intval($new_instance['max_items']);
		$instance['max_per_feed'] = intval($new_instance['max_per_feed']);
		$instance['cachetime'] = intval($new_instance['cachetime']);
		$instance['show_date'] = intval($new_instance['show_date']);
        $instance['show_prize'] = intval($new_instance['show_prize']);
		$instance['date_format'] = $new_instance['date_format'];
		return $instance;
	}
	
	
	function form($instance) {
		
		$instance = wp_parse_args((array)$instance, array(
			'title' => __('Recently started contests','DCRSS'),
			'style' => 'time', 
			'max_items' => 10,
			'max_per_feed' => 4,
			'cachetime' => 600,
			'show_date' => 0,
            'show_prize' => 1,
			'feeds' => "https://www.designcontest.com/rss.html"
			));
		
		$title = htmlspecialchars($instance['title']);
		$feeds = htmlspecialchars($instance['feeds']);
		$style = $instance['style'];
		$max_items = intval($instance['max_items']);
		$max_per_feed = intval($instance['max_per_feed']);
		$cachetime = intval($instance['cachetime']);
		$show_date = intval($instance['show_date']);
        $show_prize = intval($instance['show_prize']);
		$date_format = empty($instance['date_format']) ? get_option('date_format') : $instance['date_format'];
  
		echo '
			<p>Source:<br>'.$feeds.'</p>
			<p>
			<label for="'.$this->get_field_name('title').'">'.__('Title:','DCRSS').' </label> 
			<input type="text" id="'.$this->get_field_id('title').'" style="width:100%;" name="'.$this->get_field_name('title').'" value="'.$title.'"/>
			</p>
			<p>

			<p>
				<label for="'.$this->get_field_name('cachetime').'">'.__('Cache Period (seconds):','DCRSS').' </label>
				<input type="text" id="'.$this->get_field_id('cachetime').'" name="'.$this->get_field_name('cachetime').'" value="'.$cachetime.'" style="width:50px" />
			</p>

			<table width="400">
			<tr>
			<td width="50%">
				<p>
					<label for="'.$this->get_field_name('max_items').'">'.__('Maximum Items:','DCRSS').' </label>
					<input type="text" id="'.$this->get_field_id('max_items').'" name="'.$this->get_field_name('max_items').'" value="'.$max_items.'" style="width:50px" />
				</p>
			</td><td>
				<p>
				<label for="'.$this->get_field_name('max_per_feed').'">'.__('Items per Feed:','DCRSS').' </label>
					<input type="text" id="'.$this->get_field_id('max_per_feed').'" name="'.$this->get_field_name('max_per_feed').'" value="'.$max_per_feed.'" style="width:50px" />
				</p>
			</td></tr>
			<tr><td>
			<p>
				<input type="checkbox" id="'.$this->get_field_id('show_date').'" name="'.$this->get_field_name('show_date').'"  value="1" '.(($show_date)?'checked="checked"': '').'/>
				<label for="'.$this->get_field_name('show_date').'">'.__('Display item date','DCRSS').' </label>
			</p>

			</td><td>
				<p>
					<label for="'.$this->get_field_name('date_format').'">'.__('Date Format:','DCRSS').' </label>
					<input type="text" id="'.$this->get_field_id('date_format').'" name="'.$this->get_field_name('date_format').'" value="'.$date_format.'" style="width:80px" />
				</p>
			</td></tr>
			<tr>
			<td><p>
				<input type="checkbox" id="'.$this->get_field_id('show_prize').'" name="'.$this->get_field_name('show_prize').'"  value="1" '.(($show_prize)?'checked="checked"': '').'/>
				<label for="'.$this->get_field_name('show_prize').'">'.__('Display item prize','DCRSS').' </label>
			</p></td>
			<td></td>
			</tr>
			</table>

			
			';
	}
	
}

function DCRSS_init() {
	register_widget('DCRSS');
	load_plugin_textdomain( 'DCRSS', false, dirname(plugin_basename( __FILE__ )).'/languages' ); 
}

add_action('widgets_init', 'DCRSS_init');

?>