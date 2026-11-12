/**
 * BD Toolkit — drag-and-drop / keyboard ordering for list tables (see includes/reorder.php).
 *
 * @package bd-toolkit
 */

( function() {
	// Scoped so this file doesn't set a global $ in wp-admin.
	var $ = jQuery;

	$( function() {
		var $list = $( '#the-list' );
		var dragGroup = null;
		var saveTimer = null;
		var $lastMoved = $();

		if ( ! $list.length || 'undefined' === typeof bdtkSortable ) {
			return;
		}

		$list.closest( '.wp-list-table' ).addClass( 'bdtk_sortable_active' );

		function bdRowId( $row ) {
			return parseInt( $row.attr( 'id' ).replace( 'post-', '' ), 10 );
		}

		function bdRowParent( $row ) {
			return parseInt( $row.find( '.bdtk_sort_handle' ).attr( 'data-parent' ), 10 );
		}

		function bdRowLevel( $row ) {
			var match = /level-(\d+)/.exec( $row.attr( 'class' ) );
			return match ? parseInt( match[1], 10 ) : 0;
		}

		// Rows nested under $row (child pages) in hierarchical lists.
		function bdChildRows( $row ) {
			var level = bdRowLevel( $row );
			var $children = $();
			var $next = $row.next( 'tr' );

			while ( $next.length && bdRowLevel( $next ) > level ) {
				$children = $children.add( $next );
				$next = $next.next( 'tr' );
			}

			return $children;
		}

		// Siblings (same parent) in DOM order, each with its block of child rows.
		function bdGetGroup( parent ) {
			var group = { parent: parent, ids: [], blocks: {} };

			$list.children( 'tr[id^="post-"]' ).each( function() {
				var $row = $( this );

				if ( bdRowParent( $row ) !== parent ) {
					return;
				}

				group.ids.push( bdRowId( $row ) );
				group.blocks[ bdRowId( $row ) ] = $row.add( bdChildRows( $row ) );
			} );

			return group;
		}

		// Re-lays sibling blocks in the given order so child rows always follow their parent.
		function bdPlaceGroup( group, ids ) {
			var $after = group.parent ? $( '#post-' + group.parent ) : $();

			$.each( ids, function( i, id ) {
				var $block = group.blocks[ id ];

				if ( $after.length ) {
					$after.after( $block );
				} else {
					$list.prepend( $block );
				}

				$after = $block.last();
			} );
		}

		function bdShowError( message ) {
			$( '.bdtk_sort_notice' ).remove();
			$( '<div class="notice notice-error bdtk_sort_notice"><p></p></div>' )
				.find( 'p' ).text( message ).end()
				.insertAfter( '.wp-header-end' );
			wp.a11y.speak( message, 'assertive' );
		}

		function bdSave() {
			var order = $list.children( 'tr[id^="post-"]' ).map( function() {
				return bdRowId( $( this ) );
			} ).get();
			var $moved = $lastMoved;

			$.post( ajaxurl, {
				action: 'bdtk_sortable_save',
				nonce: bdtkSortable.nonce,
				post_type: bdtkSortable.postType,
				order: order
			} ).done( function( response ) {
				if ( ! response || ! response.success ) {
					bdShowError( ( response && response.data ) || 'The new order could not be saved. Please reload the page and try again.' );
					return;
				}

				$( '.bdtk_sort_notice' ).remove();
				wp.a11y.speak( 'Order saved.' );
				$moved.addClass( 'bdtk_sort_saved' );
				setTimeout( function() {
					$moved.removeClass( 'bdtk_sort_saved' );
				}, 1200 );
			} ).fail( function( xhr ) {
				var message = xhr.responseJSON && xhr.responseJSON.data;
				bdShowError( message || 'The new order could not be saved. Please reload the page and try again.' );
			} );
		}

		// Debounced so holding an arrow key sends one request.
		function bdQueueSave( $row ) {
			$lastMoved = $row;
			clearTimeout( saveTimer );
			saveTimer = setTimeout( bdSave, 400 );
		}

		$list.sortable( {
			items: '> tr',
			handle: '.bdtk_sort_handle',
			// jQuery UI ignores mousedown on <button> by default, and the handle is a button.
			cancel: 'input, textarea, select, option',
			axis: 'y',
			cursor: 'move',
			placeholder: 'bdtk_sort_placeholder',
			helper: function( e, $row ) {
				$row.children().each( function() {
					$( this ).width( $( this ).width() );
				} );
				return $row;
			},
			start: function( e, ui ) {
				var cols = ui.item.children( 'th, td' ).filter( ':visible' ).length;

				ui.placeholder.height( ui.item.outerHeight() ).html( '<td colspan="' + cols + '"></td>' );
				dragGroup.blocks[ bdRowId( ui.item ) ].not( ui.item ).hide();
				$list.sortable( 'refreshPositions' );
			},
			stop: function( e, ui ) {
				var id = bdRowId( ui.item );
				var ids = [];

				ui.item.children().css( 'width', '' );

				$list.children( 'tr[id^="post-"]' ).each( function() {
					if ( bdRowParent( $( this ) ) === dragGroup.parent ) {
						ids.push( bdRowId( $( this ) ) );
					}
				} );

				bdPlaceGroup( dragGroup, ids );
				dragGroup.blocks[ id ].show();

				if ( ids.join() !== dragGroup.ids.join() ) {
					bdQueueSave( ui.item );
				}
			}
		} );

		// Delegated handlers run before sortable's own mousedown, so we can limit the drop targets to
		// siblings (same parent page) before the drag starts.
		$list.on( 'mousedown', '.bdtk_sort_handle', function() {
			var parent = bdRowParent( $( this ).closest( 'tr' ) );

			dragGroup = bdGetGroup( parent );
			$list.sortable( 'option', 'items', '> tr:has(.bdtk_sort_handle[data-parent="' + parent + '"])' );
		} );

		$list.on( 'keydown', '.bdtk_sort_handle', function( e ) {
			var $handle = $( this );
			var $row = $handle.closest( 'tr' );
			var id = bdRowId( $row );
			var group, index, target;

			if ( 'ArrowUp' !== e.key && 'ArrowDown' !== e.key ) {
				return;
			}

			e.preventDefault();

			group = bdGetGroup( bdRowParent( $row ) );
			index = group.ids.indexOf( id );
			target = 'ArrowUp' === e.key ? index - 1 : index + 1;

			if ( target < 0 || target >= group.ids.length ) {
				wp.a11y.speak( 'Already at the ' + ( target < 0 ? 'top.' : 'bottom.' ) );
				return;
			}

			group.ids.splice( index, 1 );
			group.ids.splice( target, 0, id );
			bdPlaceGroup( group, group.ids );
			$handle.trigger( 'focus' );

			wp.a11y.speak( 'Moved to position ' + ( target + 1 ) + ' of ' + group.ids.length + '.' );
			bdQueueSave( $row );
		} );
	} );
} )();
