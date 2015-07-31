<?php


class FBPS_FACEBOOK {

	/**
	 * @var string
	 */
	private $app_id = '';

	/**
	 * @var string
	 */
	private $app_secret = '';

    /**
     * @var string
     */

    private $comment_status = '';

    /**
     * @var string
     */

    private $post_status = '';

	/**
	 * @var
	 */
	private $error;

	/**
	 * @param  string $app_id
	 * @param  string $app_secret
	 * @param  string $fb_id
     * @param  string $comment_status
     * @param  string $post_status
	 */
	public function __construct( $app_id, $app_secret, $fb_id = '',$comment_status = '' , $post_status = '' )
	{
		$this->app_id = $app_id;
		$this->app_secret = $app_secret;
		$this->fb_id = $fb_id;
        $this->comment_status = $comment_status;
        $this->post_status = $post_status;
	}

	/**
	 * Fetch posts from the given Facebook page.
	 *
	 *
	 */
	public function get_posts()
	{
        $result = $this->call("{$this->fb_id}/posts", array(
			'fields' => 'id,picture,type,from,message,status_type,object_id,name,caption,description,link,created_time,comments.limit(10).summary(true),likes.limit(1).summary(true)'
		));

		if( is_object( $result ) ) {
			if( isset( $result->data ) ) {
				return $this->fbps_insert_post( $result->data );
			} elseif( isset( $result->error->message ) ) {
				$this->error = __( 'Facebook error:', 'recent-facebook-posts' ) . ' <code>' . $result->error->message . '</code>';
                $status = array('status' => false , 'msg' => $this->error);
                return $status;

			}
		} 

		return false;
	}

	/**
	 * @param $data
	 *
	 * @return array
	 */
	private function fbps_insert_post( $data ) {

		$posts = array();

		foreach ( $data as $fb_post ) {

			// skip this "post" if it is not of one of the following types
			if ( ! in_array( $fb_post->type, array( 'status', 'photo', 'video', 'link' ) ) ) {
				continue;
			}

			// skip empty status updates
			if ( $fb_post->type === 'status' && ( ! isset( $fb_post->message ) || empty( $fb_post->message ) ) ) {
				continue;
			}

			// skip empty links.
			if ( $fb_post->type === 'link' && ! isset( $fb_post->name ) && ( ! isset( $fb_post->message ) || empty( $fb_post->message ) ) ) {
				continue;
			}

			// skip friend approvals
			if ( $fb_post->type === 'status' && $fb_post->status_type === 'approved_friend' ) {
				continue;
			}

            // bail out if the post already exists
            if ( $post_id = $this->is_post_exists( $fb_post->link ) ) {
                return $post_id;
            }

            $postarr = array(
                'post_type'      => 'post',
                'post_status'    => $this->post_status,
                'comment_status' => isset( $this->comment_status ) ? $this->comment_status : 'open',
                'ping_status'    => isset( $this->comment_status ) ? $this->comment_status : 'open',
                'post_author'    => 1,
                'post_date'      => gmdate( 'Y-m-d H:i:s', ( strtotime( $fb_post->created_time ) )  ),
                'guid'           => $fb_post->link
            );

            $meta = array(
                '_fb_author_id'   => $fb_post->from->id,
                '_fb_author_name' => $fb_post->from->name,
                '_fb_link'        => $fb_post->link,
                '_fb_group_id'    => $fb_post->from->id,
                '_fb_post_id'     => $fb_post->id
            );

            switch ($fb_post->type) {
                case 'status':
                    $postarr['post_title']   = wp_trim_words( strip_tags( $fb_post->message ), 10, '...' );
                    $postarr['post_content'] = $fb_post->message;
                    break;

                case 'photo':

                    if ( !isset( $fb_post->message ) ) {
                        $postarr['post_title']   = wp_trim_words( strip_tags( $fb_post->story ), 10, '...' );
                        $postarr['post_content'] = sprintf( '<p>%1$s</p> <div class="image-wrap"><img src="%2$s" alt="%1$s" /></div>', $fb_post->story, $fb_post->picture );
                    } else {
                        $postarr['post_title']   = wp_trim_words( strip_tags( $fb_post->message ), 10, '...' );
                        $postarr['post_content'] = sprintf( '<p>%1$s</p> <div class="image-wrap"><img src="%2$s" alt="%1$s" /></div>', $fb_post->message, $fb_post->picture );
                    }

                    break;

                case 'link':
                    parse_str( $fb_post->picture, $parsed_link );

                    $postarr['post_title'] = wp_trim_words( strip_tags( $fb_post->message ), 10, '...' );
                    $postarr['post_content'] = '<p>' . $fb_post->message . '</p>';

                    if ( !empty( $parsed_link['url']) ) {
                        $postarr['post_content'] .= sprintf( '<a href="%s"><img src="%s"></a>', $fb_post->link, $parsed_link['url'] );
                    } else {
                        $postarr['post_content'] .= sprintf( '<a href="%s">%s</a>', $fb_post->link, $fb_post->name );
                    }

                    break;

                default:
                    break;
            }

            $posts[] = $postarr;
            $post_id = wp_insert_post( $postarr );

            if ( $post_id && !is_wp_error( $post_id ) ) {

                if ( $fb_post->type !== 'status' ) {
                    set_post_format( $post_id, $fb_post->type );
                }

                foreach ($meta as $key => $value) {
                    update_post_meta( $post_id, $key, $value );
                }
               // adding comment
                $this->insert_comments($post_id, $fb_post->comments->data);

            }
		}

		return $posts;
	}

    /**
     * Insert comments for a post
     *
     * @param  int $post_id
     * @param  array $comments
     * @return int
     */
    private function insert_comments( $post_id, $comments ) {
        $count = 0;
        if ( $comments ) {
            foreach ($comments as $comment) {
                $comment_id = $this->insert_comment( $post_id, $comment );

                if ( $comment_id ) {
                    $count++;
                }
            }
        }

        return $count;
    }

    function insert_comment( $post_id, $fb_comment ) {
        // bail out if the comment already exists
        if ( $this->is_comment_exists( $fb_comment->id ) ) {
            return;
        }

        $commentarr = array(
            'comment_post_ID'    => $post_id,
            'comment_author'     => $fb_comment->from->name,
            'comment_author_url' => 'https://facebook.com/' . $fb_comment->from->id,
            'comment_content'    => $fb_comment->message,
            'comment_date'       => gmdate( 'Y-m-d H:i:s', ( strtotime( $fb_comment->created_time ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ),
            'comment_approved'   => 1,
            'comment_type'       => 'fb_group_post'
        );

        $meta = array(
            '_fb_author_id'   => $fb_comment->from->id,
            '_fb_comment_id'  => $fb_comment->id
        );

        $comment_id = wp_insert_comment( $commentarr );

        if ( $comment_id && !is_wp_error( $comment_id ) ) {
            foreach ($meta as $key => $value) {
                update_comment_meta( $comment_id, $key, $value );
            }
        }

        return $comment_id;
    }
    /**
     * Check if a comment already exists
     *
     * Checks via meta key in comment
     *
     * @global object $wpdb
     * @param string $fb_comment_id facebook comment id
     * @return boolean
     */
    function is_comment_exists( $fb_comment_id ) {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id FROM $wpdb->commentmeta WHERE meta_key = '_fb_comment_id' AND meta_value = %s", $fb_comment_id ) );

        if ( $row ) {
            return true;
        }

        return false;
    }
    /**
     * Check if the post already exists
     *
     */
    function is_post_exists( $fb_link_id ) {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid = %s", $fb_link_id ) );

        if ( $row ) {
            return $row->ID;
        }

        return false;
    }


	/**
	 * @param string $endpoint
	 * @param array $data
	 *
	 * @return array|bool|mixed
	 */
	private function call( $endpoint, array $data = array() )
	{

		// Only do something if an App ID and Secret is given
		if ( empty( $this->app_id ) || empty( $this->app_secret ) ) {
			return false;
		}
    	// Format URL
		$url = "https://graph.facebook.com/{$endpoint}";

		// Add access token to data array
		$data['access_token'] = "{$this->app_id}|{$this->app_secret}";

		// Add all data to URL
		$url = add_query_arg( $data, $url );
    	$response = wp_remote_get($url, array(
			'timeout' => 10,
			'headers' => array( 'Accept-Encoding' => '' ),
			'sslverify' => false
			) 
		); 

		// Did the request succeed?
		if( is_wp_error( $response ) ) {
			$this->error = __( 'Connection error:', 'recent-facebook-posts' ) . ' <code>' . $response->get_error_message() . '</code>';
			return false;
		} else {			
			$body = wp_remote_retrieve_body($response);
			return json_decode( $body );
		}
	}

	/**
	 * @return bool
	 */
	public function has_error() {
		return ( ! empty( $this->error ) );
	}

	/**
	 * @return mixed
	 */
	public function get_error_message()
	{
		if( is_object( $this->error ) ) {
			return $this->error->message;
		}

		return $this->error;		
	}
}