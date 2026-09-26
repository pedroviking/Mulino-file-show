( function () {
	'use strict';

	var nonce     = mfsManager.nonce;
	var i18n      = mfsManager.i18n;
	var dropzone  = document.getElementById( 'mfs-dropzone' );
	var grid      = document.getElementById( 'mfs-file-grid' );
	var tree      = document.getElementById( 'mfs-tree' );

	function postAjax( data ) {
		var formData = new FormData();
		Object.keys( data ).forEach( function ( key ) {
			formData.append( key, data[ key ] );
		} );
		formData.append( 'nonce', nonce );
		return fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: formData } )
			.then( function ( res ) { return res.json(); } );
	}

	// --- Upload via drop zone ---
	[ 'dragenter', 'dragover' ].forEach( function ( evt ) {
		dropzone.addEventListener( evt, function ( e ) {
			e.preventDefault();
			dropzone.classList.add( 'is-dragover' );
		} );
	} );
	[ 'dragleave', 'drop' ].forEach( function ( evt ) {
		dropzone.addEventListener( evt, function () {
			dropzone.classList.remove( 'is-dragover' );
		} );
	} );
	dropzone.addEventListener( 'drop', function ( e ) {
		e.preventDefault();
		var files = e.dataTransfer.files;
		if ( ! files || ! files.length ) {
			return;
		}
		var folderId = dropzone.getAttribute( 'data-folder-id' );
		Array.prototype.forEach.call( files, function ( file ) {
			var formData = new FormData();
			formData.append( 'action', 'mfs_upload' );
			formData.append( 'nonce', nonce );
			formData.append( 'folder_id', folderId );
			formData.append( 'file', file );
			fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: formData } )
				.then( function ( res ) { return res.json(); } )
				.then( function ( json ) {
					if ( json.success ) {
						var emptyMsg = grid.querySelector( '.mfs-empty' );
						if ( emptyMsg ) {
							emptyMsg.remove();
						}
						var wrapper = document.createElement( 'div' );
						wrapper.innerHTML = json.data.html;
						grid.appendChild( wrapper.firstElementChild );
					} else {
						alert( json.data && json.data.message ? json.data.message : i18n.uploadFailed );
					}
				} );
		} );
	} );

	// --- Drag existing document cards to move them ---
	grid.addEventListener( 'dragstart', function ( e ) {
		if ( ! e.target.classList.contains( 'mfs-manager-card' ) ) {
			return;
		}
		e.target.classList.add( 'is-dragging' );
		e.dataTransfer.setData( 'text/plain', 'doc:' + e.target.getAttribute( 'data-doc-id' ) );
	} );
	grid.addEventListener( 'dragend', function ( e ) {
		if ( e.target.classList.contains( 'mfs-manager-card' ) ) {
			e.target.classList.remove( 'is-dragging' );
		}
	} );

	// --- Rename a document ---
	grid.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mfs-rename' ) ) {
			return;
		}
		var card    = e.target.closest( '.mfs-manager-card' );
		var docId   = e.target.getAttribute( 'data-doc-id' );
		var current = card.getAttribute( 'data-doc-name' ) || '';
		var name    = prompt( i18n.renameDocPrompt, current );
		if ( ! name || name === current ) {
			return;
		}
		postAjax( { action: 'mfs_rename_doc', doc_id: docId, name: name } ).then( function ( json ) {
			if ( json.success ) {
				card.setAttribute( 'data-doc-name', name );
				card.querySelector( '.mfs-name' ).textContent = name;
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotRename );
			}
		} );
	} );

	// --- Delete a document ---
	grid.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mfs-delete' ) ) {
			return;
		}
		if ( ! confirm( i18n.deleteDocConfirm ) ) {
			return;
		}
		var docId = e.target.getAttribute( 'data-doc-id' );
		postAjax( { action: 'mfs_delete_doc', doc_id: docId } ).then( function ( json ) {
			if ( json.success ) {
				e.target.closest( '.mfs-manager-card' ).remove();
				if ( ! grid.querySelector( '.mfs-manager-card' ) && ! grid.querySelector( '.mfs-empty' ) ) {
					grid.innerHTML = '<p class="mfs-empty"></p>';
					grid.querySelector( '.mfs-empty' ).textContent = i18n.noDocuments;
				}
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotDelete );
			}
		} );
	} );

	// --- Drag existing folders in the tree to re-parent them, and use
	// the tree as drop targets for both documents and folders ---
	tree.addEventListener( 'dragstart', function ( e ) {
		if ( ! e.target.classList.contains( 'mfs-tree-row' ) ) {
			return;
		}
		var li = e.target.closest( '.mfs-tree-item' );
		if ( ! li || '0' === li.getAttribute( 'data-term-id' ) ) {
			return; // the "All" root row isn't a real, movable term
		}
		e.target.classList.add( 'is-dragging' );
		e.dataTransfer.setData( 'text/plain', 'folder:' + li.getAttribute( 'data-term-id' ) );
	} );
	tree.addEventListener( 'dragend', function ( e ) {
		if ( e.target.classList.contains( 'mfs-tree-row' ) ) {
			e.target.classList.remove( 'is-dragging' );
		}
	} );

	tree.querySelectorAll( '.mfs-tree-item' ).forEach( function ( li ) {
		li.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			li.classList.add( 'is-dragover' );
		} );
		li.addEventListener( 'dragleave', function ( e ) {
			e.stopPropagation();
			li.classList.remove( 'is-dragover' );
		} );
		li.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			li.classList.remove( 'is-dragover' );

			var raw = e.dataTransfer.getData( 'text/plain' );
			if ( ! raw || raw.indexOf( ':' ) === -1 ) {
				return;
			}
			var parts        = raw.split( ':' );
			var kind         = parts[ 0 ];
			var id           = parts[ 1 ];
			var targetFolder = li.getAttribute( 'data-term-id' );

			if ( 'doc' === kind ) {
				postAjax( { action: 'mfs_move', doc_id: id, folder_id: targetFolder } ).then( function ( json ) {
					if ( json.success ) {
						var card = grid.querySelector( '[data-doc-id="' + id + '"]' );
						if ( card ) {
							card.remove();
						}
						if ( ! grid.querySelector( '.mfs-manager-card' ) && ! grid.querySelector( '.mfs-empty' ) ) {
							grid.innerHTML = '<p class="mfs-empty"></p>';
							grid.querySelector( '.mfs-empty' ).textContent = i18n.noDocuments;
						}
					} else {
						alert( json.data && json.data.message ? json.data.message : i18n.couldNotMoveDoc );
					}
				} );
			} else if ( 'folder' === kind ) {
				if ( id === targetFolder ) {
					return; // dropped a folder onto itself
				}
				postAjax( { action: 'mfs_move_folder', term_id: id, new_parent_id: targetFolder } ).then( function ( json ) {
					if ( json.success ) {
						location.reload();
					} else {
						alert( json.data && json.data.message ? json.data.message : i18n.couldNotMoveFolder );
					}
				} );
			}
		} );
	} );

	// --- New folder ---
	document.getElementById( 'mfs-new-folder' ).addEventListener( 'click', function () {
		var name = prompt( i18n.newFolderPrompt );
		if ( ! name ) {
			return;
		}
		var currentFolderId = dropzone.getAttribute( 'data-folder-id' ) || 0;
		postAjax( { action: 'mfs_create_folder', name: name, parent_id: currentFolderId } ).then( function ( json ) {
			if ( json.success ) {
				location.reload();
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotCreateFolder );
			}
		} );
	} );

	// --- Rename folder ---
	tree.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mfs-tree-rename' ) ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var li      = e.target.closest( '.mfs-tree-item' );
		var link    = li.querySelector( ':scope > .mfs-tree-row > .mfs-tree-link' );
		var termId  = e.target.getAttribute( 'data-term-id' );
		var current = link.getAttribute( 'data-term-name' ) || link.textContent;
		var name    = prompt( i18n.renameFolderPrompt, current );
		if ( ! name || name === current ) {
			return;
		}
		postAjax( { action: 'mfs_rename_folder', term_id: termId, name: name } ).then( function ( json ) {
			if ( json.success ) {
				link.setAttribute( 'data-term-name', name );
				link.textContent = name;
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotRenameFolder );
			}
		} );
	} );

	// --- Delete folder ---
	tree.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mfs-tree-delete' ) ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var termId = e.target.getAttribute( 'data-term-id' );
		if ( ! confirm( i18n.deleteFolderConfirm ) ) {
			return;
		}
		postAjax( { action: 'mfs_delete_folder', term_id: termId } ).then( function ( json ) {
			if ( json.success ) {
				var wasSelected = e.target.closest( '.mfs-tree-item' ).classList.contains( 'is-selected' );
				e.target.closest( '.mfs-tree-item' ).remove();
				if ( wasSelected ) {
					window.location.href = mfsManager.rootUrl;
				}
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotDeleteFolder );
			}
		} );
	} );
} )();
