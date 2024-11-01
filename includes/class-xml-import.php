<?php

class XML_Import {
	
	protected $post_type = 'xmli_feed';
	
	protected $def_post_keys;
	
	/* paths */
	protected $base;
	protected $tmp;
	
	/* feed info */
	protected $root = '';
	protected $type = '';
	protected $map = array();
		
	public function __construct($base_path) {
		$this->base = $base_path;
		$this->tmp = $base_path  . 'tmp/';
	}
	
	public function load_textdomain() {
		load_plugin_textdomain( 'xml-import', false, basename( $this->base ) . '/lang/' );
	}

	public function wrapper_info( $json_encoded = true ) {
		$info = array(
			'root' => $this->root,
			'type' => $this->type,
			'map' => $this->map,
		);
		
		if( $json_encoded ) {
			return json_encode( $info );
		}
		return $info;
	}
	
	public function fill_feed_info( $post_id = null ) {
		if( is_null( $post_id ) ) {
			$post = $GLOBALS['post']; // edit page
		} else {
			$post = get_post( $post_id ); // ajax requests
		}
		
		if( $post ) {
			
			$this->def_post_keys = array_keys( get_object_vars( $post ) );
			
			$w_map = json_decode( $post->post_content, true );
//			$w_map = json_decode( stripslashes( $post->post_content ), true );
			
			if( is_array( $w_map ) ) {
				$this->root = $w_map['root'];
				$this->type = $w_map['type'];
				$this->map = $w_map['map'];
			}
			return $post;
		} // misschien een else ?
		
		return false;
	}
	
	/* hooks */
	
	public function init() {
		
		if ( ! post_type_exists( $this->post_type ) ) {
			
			$labels = array(
				'name'               => _x( 'Feeds', 'Feed type general name', 'xml-import' ),
				'singular_name'      => _x( 'Feed', 'Feed type singular name', 'xml-import' ),
				'menu_name'          => __( 'Feeds', 'xml-import' ),
				'name_admin_bar'     => __( 'Feed', 'xml-import' ),
				'add_new'            => __( 'Add New', 'xml-import' ),
				'add_new_item'       => __( 'Add New Feed', 'xml-import' ),
				'new_item'           => __( 'New Feed', 'xml-import' ),
				'edit_item'          => __( 'Edit Feed', 'xml-import' ),
				'view_item'          => __( 'View Feed', 'xml-import' ),
				'all_items'          => __( 'All Feeds', 'xml-import' ),
				'search_items'       => __( 'Search Feeds', 'xml-import' ),
				'parent_item_colon'  => __( 'Parent Feeds:', 'xml-import' ),
				'not_found'          => __( 'No feeds found.', 'xml-import' ),
				'not_found_in_trash' => __( 'No feeds found in Trash.', 'xml-import' ),
			);
			
			
			register_post_type( $this->post_type, array(
				'labels' => $labels,
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => true,
				'supports' => array( 'title', 'excerpt' ),
				'register_meta_box_cb' => array( $this, 'meta_boxes' ),
			) );
			
		}
		//add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_footer', array( $this, 'footer_javascript' ) );
	}
	
	public function row_actions( $actions, $post ) {
		if( $post->post_type == $this->post_type ) {
			$actions['import'] = '<a href="javascript:;">' . __( 'Import', 'xml-import' ) . '</a>';
		}
		return $actions;
	}
	public function meta_boxes() {
		global $post;
		
		$this->fill_feed_info();
		
		add_meta_box( 'import', __( 'Import', 'xml-import' ), function() {
			echo '<a href="javascript:;" class="import button-secondary">' . __( 'Import', 'xml-import' ) . '</a>';
			echo '<span class="spinner"></span><div id="xmli-current-import-offset"></div>';
			
			add_action('admin_footer', function() {
				global $post;
?>
				<script>$('#import a.import').bind('click', function() { $('#xmli-current-import-offset').html('<?php _e( 'Downloading .. ', 'xml-import' ); ?>'); xmliImport(<?php echo $post->ID; ?>, 0) })</script>
<?php
			});
		});
		
		remove_meta_box( 'postexcerpt', $this->post_type, 'normal' );
		add_meta_box( 'postexcerpt', 'URL', function() {
			global $post;
			echo '<input type="text" id="excerpt" name="excerpt" value="' . $post->post_excerpt . '" />';
			if( $post->post_status == 'publish' ) {
				if( file_exists( $this->tmp . '.' . $post->ID . '.xml' ) ) {
					_e( 'Local copy of feed found', 'xml-import' );
				} else {
					_e( 'No local copy of feed found', 'xml-import' );
				}
				echo ' - <a href="javascript:;" class="xmli-download-feed">' . __( 'Download new copy', 'xml-import' ) . '</a>';
				echo '<span class="spinner"></span>';
				add_action('admin_footer', function() {
					global $post;
?>
					<script>$('.xmli-download-feed').bind('click', function() { xmliDownloadFeed(<?php echo $post->ID; ?>) })</script>
<?php
				});
			}
		});
		
		//add_meta_box( 'postcontent', 'Map', array( $this, 'map_feed' ) );
		
		$path = $this->tmp . '.' . $post->ID . '.xml';
		if ( ! empty( $post->post_excerpt ) && ! file_exists( $path ) ) {
			file_put_contents( $path, $this->file_get_contents_curl( $post->post_excerpt ) );
			$csv_delimiter = get_post_meta( $post->ID, 'xmli-csv-delimiter', true );
			
			if( !empty( $csv_delimiter ) ) {
				$obj = @simplexml_load_file( $path );
				if($obj === false){
					$csv_path = $this->tmp . '.' . $post->ID . '.csv';
					rename( $path, $csv_path );
					$this->csv_to_xml( $csv_path, $path, $csv_delimiter );
				}
			}
		}
		
		add_meta_box( 'requiredfields', __( 'Required Fields', 'xml-import' ), array( $this, 'required_fields' ) );
		add_meta_box( 'uniquefields', __( 'Unique Fields', 'xml-import' ), array( $this, 'unique_fields' ) );
		add_meta_box( 'csvdelimiter', __( 'CSV Delimiter', 'xml-import' ), array( $this, 'csv_delimiter' ) );
		add_meta_box( 'selectroot', __( 'Select root', 'xml-import' ), array( $this, 'select_root' ) );
		add_meta_box( 'selectpostfield', __( 'Select post field', 'xml-import' ), array( $this, 'select_post_field' ) );
		add_meta_box( 'map', __( 'Map', 'xml-import' ), array( $this, 'map' ) );
		add_meta_box( 'assignmap', 'XML', array( $this, 'assign_map' ) );
		
		
	}
	
	public function save( $post_id, $post, $update ) {
		if( isset( $_POST['unique-fields'] ) ) {
			$unique_fields = implode( ',', array_map( function( $f ) { return trim( $f ); }, explode( ',',  $_POST['unique-fields'] ) ) );
			update_post_meta( $post_id, 'xmli-unique-fields', $unique_fields );
		}
		if( isset( $_POST['required-fields'] ) ) {
			$required_fields = implode( ',', array_map( function( $f ) { return trim( $f ); }, explode( ',',  $_POST['required-fields'] ) ) );
			update_post_meta( $post_id, 'xmli-required-fields', $required_fields );
		}
		if( isset( $_POST['csv-delimiter'] ) && ( $delimiter = trim( $_POST['csv-delimiter'] ) ) != '' ) {
			update_post_meta( $post_id, 'xmli-csv-delimiter', $delimiter );
		} else {
			delete_post_meta( $post_id, 'xmli-csv-delimiter' );
		}
	}
	
	public function admin_menu() {
		add_menu_page( 'XML Import', 'XML Import', 'manage_options', 'xmli', array( $this, 'main_page' ) );
	}
	
	public function styles() {
		global $post;
		if( ! $post || $post->post_type != $this->post_type ) {
			return;
		}
		wp_register_style( 'xmli_css', plugins_url( 'style.css', $this->base . 'style.css' ) );
		wp_enqueue_style( 'xmli_css' );
	}

	public function scripts() {
		global $post;
		if( ! $post || $post->post_type != $this->post_type ) {
			return;
		}
		wp_enqueue_script( 'jquery' );
	}
	
	/*
	public static function activate() {
		;
	}
	
	public static function deactivate() {
		;
	}
	
	public static function uninstall() {
		;
	}
	*/
	
	
	/* end hooks */
	
	
	/* Page views */
	
	public function unique_fields() {
		global $post;
		echo '<input type="text" id="unique-fields" value="' . get_post_meta($post->ID, "xmli-unique-fields", true) . '" placeholder="' . __( 'Comma-separated list of unique fields', 'xml-import') . '" name="unique-fields">';
	}
	
	public function required_fields() {
		global $post;
		echo '<input type="text" id="required-fields" value="' . get_post_meta($post->ID, "xmli-required-fields", true) . '" placeholder="' . __( 'Comma-separated list of required fields', 'xml-import') . '" name="required-fields">';
	}
	
	public function csv_delimiter() {
		global $post;
		echo '<input type="text" id="csv-delimiter" value="' . get_post_meta($post->ID, "xmli-csv-delimiter", true) . '" placeholder="' . __( 'CSV delimiter: Only fill this in if the feed is actually a CSV file!', 'xml-import') . '" name="csv-delimiter">';
	}
	
	public function select_root() {
		global $post;
?>
		<script>var xmliMap=<?php echo ( empty( $this->map ) ? '{}' : json_encode( $this->map ) ); ?></script>
		<input id="xmli-selected-root" type="hidden" value="<?php echo $this->root; ?>" />
		<div id="xmli-data" feed-id="<?php echo $post->ID; ?>" obj-n="0">
			<a href="javascript:void(0)" class="button-secondary">-</a>
			<div id="xmli-path">
			</div>
			<a href="javascript:void(0)" class="button-secondary">+</a>
			<a href="javascript:void(0)" class="button-primary"><?php _e( 'Select', 'xml-import' ); ?></a>
		</div>
<?php
	}
	public function map() {
?>
		<div id="xmli-map">
			<table>
				<tr class="xmli-map-head"><th colspan="3">Map</th></tr>
				<tr><td colspan="3" class="xmli-save-map"><a class="button-primary" href="javascript:void(0)"><?php _e( 'Save map', 'xml-import'); ?></a></td></tr>
			</table>
		</div>
<?php
	}
	public function select_post_field() {
		global $wp_post_types;
		
		echo '<select id="xmli-post-types" name="xmli-post-types" data="' . $this->type . '">';

		foreach($wp_post_types as $pt) {
			$selected = $pt->name == $this->type ? ' selected="selected"' : '' ;
			echo "<option value=\"$pt->name\"$selected>$pt->label</option>";
		}

		echo '<option value="taxonomies">' . __( 'Taxonomies', 'xml-import' ) . '</option>';
		echo '</select><div id="xmli-post-fields"></div>';
	}
	
	public function assign_map() {
?>
		<div id="xmli-assign-path-wrap">
			<h3><?php _e( 'Assign path', 'xml-import' ); ?></h3>
			<div id="xmli-assign-path"></div>
			<a href="javascript:void(0)" class="button-secondary"><?php _e( 'Add to map', 'xml-import' ); ?></a>
		</div>
		
		<div id="xmli-feed-content"></div>
<?php
	}
	
	function footer_javascript() {
		global $post;
		if( ! $post || $post->post_type != $this->post_type ) {
			return;
		}
?>
		<script type="text/javascript">
			var $ = jQuery,
				xmliCurrentObj;
			
			function xmliMapHTML() {
				var html = '', k;
				for( k in xmliMap ) {
					html += '<tr class="xmli-map-field"><td class="xmli-map-key">' + k + '</td><td class="xmli-map-value">' + xmliMap[ k ] + '</td><td><a href="javascript:void(0)">X</a></td></tr>';
				}
				$( '#xmli-map tr.xmli-map-field' ).each( function() { this.remove() } );
				$( '#xmli-map tr.xmli-map-head' ).after( html );
				$( '#xmli-map tr.xmli-map-field a' ).bind( 'click', function() {
					var p = this.parentNode.parentNode;
						k = $( p ).find( '.xmli-map-key' ).html();
					
					delete xmliMap[ k ];
					p.remove();
				} );
			}
			function xmliGet( to ) {
				if( to === -1 ) {
					$( '#xmli-data select' ).last().remove();
					$('#xmli-data span').last().remove();
					return;
				}
				
				if( to !== 0 && to !== 1 ) {
					to = 0;
				}

				var selects = $( '#xmli-data select' ),
					path = '',
					data = {
						'action': 'xmli_get_level',
						'to': to,
						'id': $( '#xmli-data' ).attr( 'feed-id' ),
					};
	
				$.each( selects, function( k, v ) {
					path += '/' + $( v ).val();
				} );

				data.path = path;
				
				$.post( ajaxurl, data, function( response ) {
					if( ! response.os.length ) {
						return;
					}

					switch ( response.to ) {
						case 0:
							$( '#xmli-data select' ).last().remove();

						case 1:
							var html = '<span>/</span><select>';
							$.each( response.os, function( k, v ){
								html += '<option value="' + v + '">' + v + '</option>';
							} );
							html += '</select>';
							$( '#xmli-path' ).append( html );
							
							break;

						default:
							break;
							
					}
					
				}, 'json' );
			}
			
			function xmliAttrToString( attr, base ) {
				var ret = '',
					k, kb,
					path = '';
				
				if( base ) {
					path = base;
				}
				
				for( k in attr ) {
					kb = path + '@' + k;
					ret += '<span path="' + kb + '" class="attr-key">' + k + '</span>=<span path="' + kb + '=' + attr[ k ] + '" class="attr-val">' + attr[ k ] + '</span>';
				}
				return ret;
			}
			
			function xmliKVSelect( e ) {
				if( ! $( e.target ).attr( 'path' ) || $( e.target ).is( '#xmli-assign-path span:last' ) ) {
					return;
				}

				var tags = $( e.target ).attr( 'path' ).split( '/' ),
					obj = xmliCurrentObj,
					i, j, k, s1, s2, keysList = {};

				$( '#xmli-assign-path' ).attr( 'path', $( e.target ).attr( 'path' ) );

				for( i = 0 ; i < tags.length ; i++ ) {
					if ( tags[ i ] == "" || ! obj[ tags[ i ] ] ) {
						continue;
					}
					
					obj = obj[ tags[ i ] ];
					
					if( typeof obj == 'object' && obj.length ) {
						if ( i+1 == tags.length ) {

							for ( j = 0 ; j < obj.length ; j++ ) {
								for ( k in obj[ j ] ) {

									if ( k == '@attributes' ) {
										continue;
									}

									if( ! keysList[ k ] ) {
										keysList[ k ] = [];
									}
									keysList[ k ].push( obj[ j ][ k ] );
								}

							}
						} else {
							// voorlopig maar de eerste pakken
							obj = obj[0];
						}
					} else if ( i+1 == tags.length ) {
						for ( j in obj ) {

							if ( j == '@attributes' || ! ( typeof obj[ j ] == 'string' || typeof obj[ j ] == 'number' ) ) {
								continue;
							}

							if( ! keysList[ j ] ) {
								keysList[ j ] = [];
							}

							keysList[ j ].push( obj[ j ] );
						}
					}
					
				}

				if( $.isEmptyObject( keysList ) ) {
					return;
				}
				s1 = '<select class="keys">';
				s2 = '';
				k = '';
				for ( i in keysList ) {
					s1 += '<option value="' + i + '">' + i + '</option>';
					s2 += '<select class="values values-' + i + k + '">';

					for ( j = 0; j < keysList[ i ].length; j++ ) {
						s2 += '<option value="' + keysList[ i ][ j ] + '">' + keysList[ i ][ j ] + '</option>';
					}
					s2 += '</select>';
					k = ' hidden';
				}

				s1 += '</select>';
				
				if( $( '#xmli-kv-select' ).length == 0 ) {
					$( '#xmli-assign-path' ).after( '<div id="xmli-kv-select"></div>' );
				}
				$( '#xmli-kv-select' ).html( s1 + s2 + '<a href="javascript:void(0)" class="button-secondary"><?php _e( 'Add to attribute list', 'xml-import' ); ?></a>' );

				$( '#xmli-kv-select .keys' ).bind( 'change', function( e ) {
					$( this ).parent().find( '.values' ).addClass( 'hidden' );
					//$(this).val();
					$( this ).parent().find( '.values-' + $( this ).val() ).removeClass( 'hidden' );
				} );

				$( '#xmli-kv-select a' ).bind( 'click', function( e ) {
					e.preventDefault();
					var p = $( this ).parent(),
						v = $( p ).find( '.keys' ).val(),
						bc = v + '="' + $( p ).find( '.values-' + v ).val() + '"',
						apd = $( '#xmli-assign-path' ),
						fs = $( apd ).find( 'span[path="' + $( apd ).attr( 'path' ) + '"]' ),
						as = fs[0].nextElementSibling,
						r = new RegExp( '[ \[]' + v + '="[^"]+"' );

					if( as.tagName == 'SPAN' && as.className == 'brackets' ) { // er zijn al attributen
						
						if( $( as )[0].textContent.match( r ) ) { // er is al een attribuut met deze key
							$( as ).contents().filter( function() {
								return this.nodeType === 3;
							} ).each( function( k, v ) {
								v.textContent = v.textContent.replace( r, ' ' + bc );
							} );
						} else { // er is nog geen attribuut met deze key
							$( as ).html( $( as ).html().replace( /]$/, ' AND ' + bc + ']' ) );	
						}
						
					} else { //er zijn nog geen attributen
						$( fs ).after( '<span class="brackets">[' + bc + ']</span>' );
					}

				} );
			}
			function xmliAttrToSelect( attr ) {
				var k, i,
					ret = '';
				
				for( k in attr ) {
					ret += '<span> AND @' + k + '="<select name="' + k + '"><option value="[any]"><?php _e( 'Any' ,'xml-import' ) ?></option>';
					
					if( typeof attr[ k ] == 'object' && attr[ k ].length ) {
						for( i = 0 ; i < attr[ k ].length ; i++ ){
							ret += '<option value="' + attr[ k ][ i ] + '">' + attr[ k ][ i ] + '</option>';
						}
					} else {
						ret += '<option value="' + attr[ k ] + '">' + attr[ k ] + '</option>';
					}
					
					ret += '</select>"</span>';
				}
				return ret.replace( ' AND ', '' );
			}
			function xmliBeautify( obj, tag, base ) {
				
				var html, t, i, ts, path;
				
				if( base ) {
					path = base + '/' + tag;
				} else {
					path = tag;
				 }
				
				tag = tag.split( '/' ).pop();
				
				html = '<div>&lt;<span path="' + path + '" class="tag">' + tag + '</span>';
				
				if( obj['@attributes'] ) {
					html += xmliAttrToString( obj['@attributes'], path );
				}
				html += '&gt;';
				
				if( isNaN( obj.length ) ) {
					for( t in obj ) {
						if( t == '@attributes' ) {
							continue;
						}
						
						if( typeof obj[ t ] == 'object' ) {
							if( obj[ t ].length ) {
								for( i = 0 ; i < obj[ t ].length ; i++ ) {
									html += xmliBeautify( obj[ t ][ i ], t, path );
								}
							} else {
								html += xmliBeautify( obj[ t ], t, path );
							}
						} else {
							html += '<div>&lt;<span path="' + path + '/' + t + '" class="tag">' + t + '</span>&gt;<span class="inner-val">' + obj[ t ] + '</span>&lt;/<span path="' + path + '/' + t + '" class="tag">' + t + '</span>&gt;</div>';
						}
					}
				}
				html += '&lt;/<span path="' + path + '" class="tag">' + tag + '</span>&gt;</div>';
				
				return html;
			}
			function xmliPathToSelect( path ){
				var tags = path.substr( $( '#xmli-selected-root' ).val().length + 1 ).split( '/' ),
					obj = xmliCurrentObj,
					i, j, k,
					localAttr = {},
					out = $( '#xmli-selected-root' ).val();
				
				//if(obj['@attributes']){
				//	out += '[' + xmliAttrToSelect(obj['@attributes']) + ']';
				//}
				
				for( i = 0 ; i < tags.length ; i++ ) {
					if ( tags[ i ] == "" ) {
						continue;
					}
					out += '/<span path="' + tags.slice( 0, i + 1 ).join( '/' ) + '">' + tags[ i ] + '</span>';
					
					obj = obj[ tags[ i ] ];
					
					if( typeof obj == 'object' && obj.length ) {
						for( j = 0 ; j < obj.length ; j++ ) {
							if( obj[ j ][ '@attributes' ] ) {
								for( k in obj[ j ][ '@attributes' ] ) {
									if( typeof localAttr[ k ] != 'object' ) {
										localAttr[ k ] = [];
									}
									localAttr[ k ].push( obj[ j ][ '@attributes' ][ k ] );
								}
							}
						}
						if( localAttr ) {
							localAttr = xmliAttrToSelect( localAttr );
							if( localAttr != '' ) {
								out += '<span class="brackets">[' + localAttr + ']</span>';
							}
							localAttr = {};
						}
						obj = obj[0];
						
					} else {
						if( obj[ '@attributes' ] ) {
							out += '<span class="brackets">[' + xmliAttrToSelect( obj[ '@attributes' ] ) + ']</span>';
						}
					}
					
				}
				$( '#xmli-assign-path').html( out );
				$( '#xmli-assign-path-wrap').css( { display : 'block' } );
			}
			function xmliSelectRoot( path ) {
				if( ! path ) {
					path = $( '#xmli-selected-root' ).val();
				}
				
				var data = {
					'action': 'xmli_select_root',
					'path' :path,
					'id': $( '#xmli-data' ).attr( 'feed-id' ),
					'n': $( '#xmli-data' ).attr( 'obj-n' ),
				};
				$.post( ajaxurl, data, function( response ) {

					if( ! response.path ||  ! response.obj ) {
						return;
					}
					
					$( '#xmli-selected-root' ).val( response.path );
					xmliCurrentObj = response.obj;
					$( '#xmli-feed-content' ).html( xmliBeautify( response.obj, response.path ) );
					
					$( '#xmli-feed-content span' ).bind( 'click', function() {
						var tagEl = this,
							attrKV = [];
						
						if( tagEl.className == 'attr-key' ) {
							attrKV.push( tagEl.innerHTML );
							attrKV.push( tagEl.nextElementSibling.innerHTML );
						} else if( tagEl.className == 'attr-val' ) {
							attrKV.push( tagEl.previousElementSibling.innerHTML );
							attrKV.push( tagEl.innerHTML );
						}
						
						while( tagEl.className != 'tag' ){
							tagEl = tagEl.previousElementSibling;
						}
						xmliPathToSelect( $( tagEl ).attr( 'path' ) );
						$( '#xmli-assign-path span' ).bind( 'click', xmliKVSelect );
						if( attrKV.length ) {
							$( '#xmli-assign-path span[path="' + $( tagEl ).attr( 'path' ).replace( $( '#xmli-selected-root' ).val(), '' ).substr(1) + '"]').next( '.brackets' ).find( 'select[name="' + attrKV[0] + '"]' ).val( attrKV[1] );
						}
						
						if( $( '#xmli-kv-select' ).length == 0 ) {
							$( '#xmli-assign-path' ).after( '<div id="xmli-kv-select"></div>' );
						}
						$( '#xmli-kv-select' ).html( '' );
					});
					
				}, 'json' );
			}
			function xmliImport(id, offset) {
				if( ! id ) {
					return;
				}
				if( ! offset ) {
					offset = 0;
				}
				
				var data = {
					'action': 'xmli_import_map',
					'id': id,
					'offset': offset,
				};
				$.post( ajaxurl, data, function( response ) {
				
					var offset = $( '#xmli-current-import-offset' );
					
					if( response.error ) {
						alert( response.error );
						$( offset ).html( '' );
						$( offset ).prev( '.spinner' ).css( { visibility : 'hidden' } );
					} else {
						if( response.la ) {

							$( offset ).prev( '.spinner' ).css( { visibility : 'hidden' } );
							$( offset ).html( response.no + ' <?php _e( 'posts imported', 'xml-import' ); ?>' );
							
						} else {

							$( offset ).prev( '.spinner' ).css( { visibility : 'visible' } );
							$( offset ).html( response.no );
							xmliImport( response.id, response.no );

						}
					}
				}, 'json' );
			}
			
			function xmliDownloadFeed( id ) {

				if( ! id ) {
					return;
				}
				
				var data = {
					'action': 'xmli_download_feed',
					'id': id,
				};

				$( '.xmli-download-feed' ).next( '.spinner' ).css( { visibility : 'visible' } );
				$.post( ajaxurl, data, function( response ) {
					alert( response );
					$( '.xmli-download-feed' ).next( '.spinner' ).css( { visibility : 'hidden' } );
				});
			}
			
			( function() {
				$( '#xmli-data > a.button-primary' ).bind( 'click', function() {
					var path = '';
					$.each( $( '#xmli-data select' ), function( k, v ) {
						path += '/' + $( v ).val();
					} );
					xmliSelectRoot( path );
				});
				
				$( '#xmli-data > a.button-secondary' ).bind( 'click', function() {
					var a = eval( "0" + $( this ).html() + "1" );
					xmliGet( a );
				} );
				$( '#xmli-assign-path-wrap > a.button-secondary' ).bind( 'click', function() {
					var pathHtml = $( '#xmli-assign-path' ).html(),
						key = $( '#xmli-post-fields select' ).val(),
						messyText;
					
					if( ! key ) {
						return;
					}
					
					$( '#xmli-assign-path select' ).each( function( k, v ) {
						if( $( v ).val() == '[any]' ) {
							this.parentNode.remove();
						} else {
							$( v ).after( $( v ).val() );
						}
					} );
					$( '#xmli-assign-path select' ).each( function( k, v ) {
						this.remove();
					} );
					
					messyText = $( '#xmli-assign-path' ).text();
					messyText = messyText.replace( /\[\]/g, '' ); // lege attribute selecties weghalen
					messyText = messyText.replace( /\[ AND /g, '[' ); // als de eerste is weggehaald blijft er zo AND over aan het begin
					
					if( $( '#xmli-post-types' ).val() == 'taxonomies' ) {
						key = 'tax:' + key;
					}
					
					xmliMap[ key ] = messyText;
					$( '#xmli-assign-path' ).html( pathHtml );
					
					//$('#xmli-post-types').attr('disabled', 'disabled');
					xmliMapHTML();
				} );
				$( '#xmli-map .xmli-save-map > a.button-primary' ).bind( 'click', function() {
					var data = {
						'action': 'xmli_save_map',
						'map' : JSON.stringify( xmliMap ),
						'root' : $( '#xmli-selected-root' ).val(),
						'type' : $( '#xmli-post-types' ).attr( 'data' ),
						'id' : $( '#xmli-data' ).attr( 'feed-id' ),
					};
					$.post( ajaxurl, data, function( response ) {
						alert( response );
					} );
				} );
				$( 'a.xmli-download' ).bind( 'click', function() {
					xmliDownloadFeed( $( this ).attr( 'sid' ) );
				} );
				if( $( '#xmli-selected-root' ).length ) {
					//var nSelects = $( '#xmli-selected-root' ).val().split( '/' ).length - 1;
					xmliGet();
					xmliMapHTML();
				}
				$( '#xmli-post-types' ).bind( 'change', function() {
					var data = {
						'action': 'xmli_select_changed',
						'type': $( this ).val(),
					};
					
					if( $( this ).val() != 'taxonomies' ) {
						$( this ).attr( 'data', $( this ).val() );
					}
					
					$.post( ajaxurl, data, function( response ) {
						var html;
						if( typeof response == 'object' && response.error ) {
							html = response.error;
						} else {
							html = '<select name="xmli-post-fields">';
							$.each( response, function( k, v ) {
								html += '<option value="' + v + '">' + v + '</option>';
							} );
							html += '</select>';
						}
						$('#xmli-post-fields').html( html );
					}, 'json' );
				} );
				$( '#xmli-post-types' ).trigger( 'change' );
			})();
			
		</script>
<?php
	}
	
	/* End Page views */
	
	
	/* Ajax callbacks */
	
	public function download_feed() {
		$post = $this->fill_feed_info( $_POST['id'] );
		
		if( $post ) {
			$path = $this->tmp . '.' . $post->ID . '.xml';
			file_put_contents( $path, $this->file_get_contents_curl( $post->post_excerpt ) );
			
			$csv_delimiter = get_post_meta( $post->ID, 'xmli-csv-delimiter', true );
			
			if( !empty( $csv_delimiter ) ) {
				$obj = @simplexml_load_file( $path );
				if($obj === false){
					$csv_path = $this->tmp . '.' . $post->ID . '.csv';
					rename( $path, $csv_path );
					$this->csv_to_xml( $csv_path, $path, $csv_delimiter );
				}
			}
			
			_e( 'Download complete', 'xml-import' );
		} else {
			_e( 'Could not download', 'xml-import' );
		}
		wp_die();
	}
	
	public function import_map() {
		$user = wp_get_current_user();
		
		$post = $this->fill_feed_info( $_POST['id'] );
		$offset = (int) $_POST['offset'];
		$size = 10;
		
		if( ! $post ) {
			echo '{"error":"' . __( 'Invalid input', 'xml-import' ) . '"}';
			wp_die();
		}
		
		$map = array();

		foreach( $this->map as $key => $value ) {
			$map[ $key ] = trim( preg_replace( '/^'.str_replace('/', '\/', $this->root) .'/', '', $value ), '/' );
		}
		
		$unique_fields = explode( ',', get_post_meta( $post->ID, 'xmli-unique-fields', true ) ); // mag leeg zijn
		
		if( ! isset( $map['post_title'] ) ) {
			echo '{"error":"' .  __( 'Required field \'post_title\' is not mapped', 'xml-import' ) . '"}';
			wp_die();
		}
		$title_path = $map['post_title'];
		unset( $map['post_title'] );
		
		$required_fields = explode( ',', get_post_meta( $post->ID, 'xmli-required-fields', true ) );
		//$required_fields_assoc = array();
		foreach( $required_fields as $f ) {
			if( empty( $f ) || $f == 'post_title' ) {
				continue;
			}
			
			if( ! isset( $map[ $f ] ) ) {
				echo '{"error":"' . sprintf( __( 'Required field \'%s\' is not mapped', 'xml-import' ), $f ) . '"}';
				wp_die();
			}
			
		//	$required_fields_assoc[ $f ] = $map[ $f ];
			
		//	unset( $map[ $f ] );
		}
		
		//unset( $required_fields );
		
		$tmp_file = $this->tmp . '.' . $post->ID . '.xml';
		$xml = simplexml_load_file($tmp_file, 'SimpleXMLElement', LIBXML_NOCDATA);

		$root_el = $xml->xpath( $this->root );
		$base_post = array(
			'post_author' => $user->ID,
			'post_content' => '',
			'post_status' => "publish",
		//	'post_title' => 'test_naam',
			'post_type' => $this->type,

		);
		
		$return = array(
			'id' => $post->ID,
			'no' => $offset + $size,
			'la' => false,
		);
		
		
		$last_el = count($root_el) - 1;
		for( $i = $offset; $i < $return['no'] && $i <= $last_el; $i ++ ) {
			
			$x = $root_el[ $i ];
			if ( $i == $last_el ) {
				$return['la'] = true;
				$return['no'] = $i;
			}
			$cpost = $base_post;
			$post_meta = array();
			$tax_terms = array();
			
			/*  
			 * De post_title is nodig om een WP_Post object aan te maken.
			 */
			
			$val = $x->xpath( $title_path );
			$cpost['post_title'] = isset( $val[0] ) ? $val[0]->__toString() : '';
			if( $cpost['post_title'] == '' ) {
				continue;
			}
			
			$_th_val = false;
			
			foreach( $map as $field => $path ) {
				
				$unique = in_array( $field, $unique_fields );
				$required = in_array( $field, $required_fields );
				
				$val = $x->xpath( $path );
				$count = count( $val );
				if($count == 0) {
					
					if( $required ) {
						continue 2;
					}
					
					continue ;
					
				} elseif ( $count == 1 ) {
					$value = $val[0]->__toString();
				} else {
					$value = array_map( function ( $v ) { return $v->__toString(); }, $val );
				}
				
				if( $required && empty( $value ) ) {
					continue 2;
				}

				if( $field == '_thumbnail_id' ) {
					
					$_th_val = is_array( $value ) ? array_pop( $value ) : $value;
					
				} elseif( strpos( $field, 'tax:' ) === 0 ) {
					
					$tax_terms[ substr( $field, 4 ) ] = $value;
					
				} elseif( in_array( $field, $this->def_post_keys ) ) {
					
					if( is_array( $value ) ) {
						$value = json_encode( $value );
					}
					
					if( $unique ) {
						$current_post = get_posts(array(
							'post_type' => $this->type,
							$field => $value,
						));
						
						if( ! empty( $current_post ) ) {
							continue 2;
						}
					}
					
					$cpost[ $field ] = $value;
				} else {
					
					if( is_array( $value ) ) {
						$value = json_encode( $value );
					}
					
					if( $unique ) {
						
						$current_post = get_posts(array(
							'post_type' => $this->type,
							'meta_key' => $field,
							'meta_value' => $value,
						));
						
						if( ! empty( $current_post ) ) {
							continue 2;
						}
					}
					$post_meta[ $field ] = $value;
				}
			}
			
			$post_id = wp_insert_post( $cpost );
			//$cpost['ID'] = $post_id;
			foreach( $post_meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
			
			foreach( $tax_terms as $key => $value ) {
				wp_set_object_terms( $post_id, $value, $key );
			}
			if( $_th_val ) {
				$_th_id = get_post_meta( $post_id, '_thumbnail_id', true );
				if( empty( $_th_id ) ) {
					$this->add_image( $post_id, $_th_val );
				}
			}
			
		}
		echo json_encode($return);
		wp_die();
	}
	
	protected function add_image( $post_id, $value, $meta_key = '_thumbnail_id' ) {
		$user = wp_get_current_user();
		$upload_dir = wp_upload_dir();
		$clean_url = filter_var( $value, FILTER_SANITIZE_URL );
		if( $value == $clean_url && ( $url = filter_var( $clean_url, FILTER_VALIDATE_URL ) ) ) {
		
			$img_str = $this->file_get_contents_curl( $url );
			$img_info = getimagesizefromstring( $img_str );

			$name = substr( $url, strrpos( $url, '/' ) + 1 );
			$ext = image_type_to_extension( $img_info[2] );

			if(
			! (
					( strtolower ( substr( $name, strlen( $name ) - strlen( $ext ) ) ) === $ext )
				||
					(
							$ext == '.jpeg'
						&&	strtolower ( substr( $name, strlen( $name ) - 4 ) ) === '.jpg'
					)
			)
			) {
				$name .= $ext;
			}
			file_put_contents( $upload_dir['path'] . '/' . $name, $img_str );
			
			$img_editor = wp_get_image_editor( $upload_dir['path'] . '/' . $name );
			$img_editor->save( $upload_dir['path'] . '/' . $name );
			$img_meta = $img_editor->get_size();
			$sizes = $img_editor->multi_resize( get_image_sizes() );
			$img_meta['file'] = trim( $upload_dir['subdir'], '/' ) . '/' . $name;
			$img_meta['sizes'] = $sizes;
			$img_meta['image_meta'] = array();
			
			$attach_id = wp_insert_post( array (
				'post_author' => $user->ID,
				'post_title' => $name,
				'post_name' => $name,
				'post_content' => '',
				'post_status' => "publish",
				'post_type' => "attachment",
				'post_mime_type' => image_type_to_mime_type( $img_info[2] ),
				'guid' => $upload_dir['url'] . '/' . $name,
			) ) ;
			
			add_post_meta( $attach_id, '_wp_attachment_metadata', $img_meta );
			add_post_meta( $attach_id, '_wp_attached_file', $img_meta['file'] );
			add_post_meta( $post_id, $meta_key, $attach_id );
		}
	}
	
	public function save_map() {
		
		$post = $this->fill_feed_info( $_POST['id'] );
		
		if( ! $post ) {
			_e( 'Could not find post', 'xml-import' );
			wp_die();
		}
		
		$map = json_decode( stripslashes( $_POST['map'] ) );
		
		if( $map ) {
			
			$this->type = $_POST['type'];
			$this->root = $_POST['root'];
			$this->map = $map;
			
			$post->post_content = $this->wrapper_info();
			wp_update_post( $post );
			_e( 'Map saved successfully', 'xml-import' );
			
		} else {
			_e( 'Could not save map', 'xml-import' );
		}
		
		wp_die();
	}
	
	public function select_root_callback() {
		$path = $_POST['path'];
		$id = (int) $_POST['id'];
		$n = (int) $_POST['n'];
		
		$tmp_file = $this->tmp . '.' . $id . '.xml';
		$xml = simplexml_load_file( $tmp_file, 'SimpleXMLElement', LIBXML_NOCDATA );
		
		$paths = $xml->xpath( $path );
		$objs = current( $paths );
		
		$response = array(
			'path' => $path,
			'obj' => $this->to_array( $objs[ $n ] ),
		);
		
		echo json_encode( $response );
		
		wp_die();
	}
	
	public function get_level() {
		$path = $_POST['path'];
		$id = (int) $_POST['id'];
		$to = (int) $_POST['to'];
		
		if( $to < 0 || $to > 1 ) {
			$to = 0;
		}
		
		$tmp_file = $this->tmp . '.' . $id . '.xml';
		$xml = simplexml_load_file( $tmp_file, 'SimpleXMLElement', LIBXML_NOCDATA );
		
		if( trim( $path, '/' ) == '' ) {
			echo json_encode ( array( 'to' => 1 , 'os' => array( $xml->getName() ) ) ) ;
		} else {
			
			if( $to === 0 ) {
				$path_array = explode( '/', $path );
				array_pop( $path_array );
				$path = '/' . implode( '/', $path_array );
			}
			if( trim( $path, '/' ) == '' ) {
				echo json_encode ( array('to' => 1, 'os' => array( $xml->getName() ) ) ) ;
			} else {
			
				$paths = $xml->xpath( $path );
				$children_keys = array();
				foreach( $paths as $p ) {
					if( $p->count() === 0 ) {
						continue;
					}
					$cs = array_keys( get_object_vars( $p ) );
					$children_keys = array_merge( $children_keys, $cs );
				}
				$out = array_unique( $children_keys );
				$out = array_filter( $out, function( $k ) { return $k != '@attributes'; } );
				
				echo json_encode( array('to' => 1, 'os' => array_values( $out ) ) );
			}
		}
		wp_die();
	}
	public function select_changed() {
		global $wp_post_types;
		
		if( isset( $_POST['type'] ) ) {
			
			if( $_POST['type'] == 'taxonomies' ) {
				
				echo json_encode( array_values( get_taxonomies() ) );
			
			} elseif( $post_type = get_post_type_object( $_POST['type'] ) ) {
				
				$p = get_posts( array ( 'post_type' => $post_type->name, 'posts_per_page' => 1 ) );
				if( $p ) {
					
					$this->fill_feed_info( $p[0]->ID );
					
					$p = get_post_custom_keys( $p[0]->ID );
					if( $p ) {
						$keys = array_merge( $this->def_post_keys, array_values( $p ) );
					} else {
						$keys = $this->def_post_keys;
					}
					
					echo json_encode( $keys );
					
				} else {
					echo '{"error":"' . sprintf( __( 'Make at least one %s item as an example for the XML importer', 'xml-import' ), $post_type->labels->singular_name ) . '"}';
				}
			}
		}
		
		wp_die();
	}
	
	/* End Ajax callbacks */
	
	
	
	
	/* Helper functions */
	
	
	/* 
	 * adapted from http://php.net/manual/en/class.simplexmlelement.php#108867 
	 */
	public function to_array( $obj ){
		$data = $obj;
		$result = array();
		if( is_object( $data ) ) {
			$data = get_object_vars( $data );
		}
		
		if( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$result[ $key ] = $this->to_array( $value );
			}
		} else {
			$result = $data;
		}
		return $result;
	}
	
	/* 
	 * adapted from http://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes
	 */
	public function get_image_sizes() {
		global $_wp_additional_image_sizes;
		$sizes = array();
		$get_intermediate_image_sizes = get_intermediate_image_sizes();

		// Create the full array with sizes and crop info
		foreach( $get_intermediate_image_sizes as $_size ) {

			if ( in_array( $_size, array( 'thumbnail', 'medium', 'large' ) ) ) {
				
				$sizes[ $_size ]['width'] = get_option( $_size . '_size_w' );
				$sizes[ $_size ]['height'] = get_option( $_size . '_size_h' );
				$sizes[ $_size ]['crop'] = (bool) get_option( $_size . '_crop' );
			
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
			
				$sizes[ $_size ] = array( 
					'width' => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
					'crop' =>  $_wp_additional_image_sizes[ $_size ]['crop']
				);
			
			}
			
		}
		return $sizes;
	}
	// http://stackoverflow.com/a/3535850
	public function file_get_contents_curl( $url ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$data = curl_exec( $ch );
		curl_close( $ch );

		return $data;
	}
	
	//http://stackoverflow.com/a/4853122
	function csv_to_xml( $inputfile, $outputfile, $delimiter ) {
		
		$file  = fopen( $inputfile, 'rt' );
		$headers = fgetcsv( $file, 0, $delimiter ); // neemt aan dat de eerste regel de namen bevat
		
		$doc  = new DomDocument();
		$root = $doc->createElement('products');
		$root = $doc->appendChild( $root );
		
		$headers = array_filter( $headers, function( $h ) { return $h != ''; } );
		
		while ( ( $row = fgetcsv( $file, 0, $delimiter ) ) !== false ) {
			$container = $doc->createElement('product');

			foreach ( $headers as $i => $header ) {
				$child = $doc->createElement( $header );
				$child = $container->appendChild( $child );
				$value = $doc->createTextNode( $row[ $i ] );
				$value = $child->appendChild( $value );
			}

			$root->appendChild( $container );
		}
		
		fclose( $file );
		
		$strxml = @$doc->saveXML();
		$handle = fopen( $outputfile, 'w' );
		fwrite( $handle, $strxml );
		fclose( $handle );
	}
	/* End Helper functions */

}
