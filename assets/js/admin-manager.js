( function () {
	'use strict';

	var nonce     = mulinoManager.nonce;
	var i18n      = mulinoManager.i18n;
	var dropzone  = document.getElementById( 'mulino-dropzone' );
	var grid      = document.getElementById( 'mulino-file-grid' );
	var tree      = document.getElementById( 'mulino-tree' );

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
		uploadFiles( Array.prototype.slice.call( files ), dropzone.getAttribute( 'data-folder-id' ) );
	} );

	// Fill in "%1$s"/"%2$d"-style placeholders in a translated string.
	function format( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var next = 0;
		return str.replace( /%(?:(\d+)\$)?[sd]/g, function ( match, pos ) {
			return String( args[ pos ? pos - 1 : next++ ] );
		} );
	}

	// Upload one file at a time, so dropping fifty files doesn't fire
	// fifty requests at the web host at once, and so the progress bar
	// and the list of failures can be kept in one place.
	function uploadFiles( files, folderId ) {
		var status   = document.getElementById( 'mulino-upload-status' );
		var text     = status.querySelector( '.mulino-upload-text' );
		var progress = status.querySelector( '.mulino-upload-progress' );
		var errors   = status.querySelector( '.mulino-upload-errors' );
		var total    = files.length;
		var uploaded = 0;
		var index    = 0;

		status.hidden   = false;
		progress.hidden = false;
		errors.innerHTML = '';

		function addError( message ) {
			var li = document.createElement( 'li' );
			li.textContent = message;
			errors.appendChild( li );
		}

		function next() {
			if ( index >= total ) {
				text.textContent = format( i18n.uploadSummary, uploaded, total );
				progress.hidden  = true;
				return;
			}

			var file = files[ index ];
			text.textContent = format( i18n.uploadingProgress, index + 1, total );
			progress.value   = index / total;

			// Catch files the host will refuse anyway before sending
			// them -- above PHP's post_max_size the request arrives
			// empty and fails with a confusing "-1".
			if ( mulinoManager.maxUploadSize && file.size > mulinoManager.maxUploadSize ) {
				addError( format( i18n.fileTooLarge, file.name, i18n.maxUploadSizeText ) );
				index++;
				next();
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'mulino_upload' );
			formData.append( 'nonce', nonce );
			formData.append( 'folder_id', folderId );
			formData.append( 'file', file );

			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', ajaxurl );
			xhr.upload.addEventListener( 'progress', function ( ev ) {
				if ( ev.lengthComputable ) {
					progress.value = ( index + ev.loaded / ev.total ) / total;
				}
			} );
			xhr.addEventListener( 'load', function () {
				var json = null;
				try {
					json = JSON.parse( xhr.responseText );
				} catch ( err ) {
					json = null;
				}
				if ( json && json.success ) {
					uploaded++;
					var emptyMsg = grid.querySelector( '.mulino-empty' );
					if ( emptyMsg ) {
						emptyMsg.remove();
					}
					var wrapper = document.createElement( 'div' );
					wrapper.innerHTML = json.data.html;
					grid.appendChild( wrapper.firstElementChild );
				} else if ( json && json.data && json.data.message ) {
					addError( file.name + ': ' + json.data.message );
				} else if ( xhr.status >= 400 ) {
					addError( file.name + ': ' + format( i18n.serverRejected, xhr.status ) );
				} else {
					addError( file.name + ': ' + i18n.uploadFailed );
				}
				index++;
				next();
			} );
			xhr.addEventListener( 'error', function () {
				addError( file.name + ': ' + i18n.uploadFailed );
				index++;
				next();
			} );
			xhr.send( formData );
		}

		next();
	}

	// --- Drag existing document cards to move them ---
	grid.addEventListener( 'dragstart', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-manager-card' ) ) {
			return;
		}
		e.target.classList.add( 'is-dragging' );
		e.dataTransfer.setData( 'text/plain', 'doc:' + e.target.getAttribute( 'data-doc-id' ) );
	} );
	grid.addEventListener( 'dragend', function ( e ) {
		if ( e.target.classList.contains( 'mulino-manager-card' ) ) {
			e.target.classList.remove( 'is-dragging' );
		}
	} );

	// --- Rename a document ---
	grid.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-rename' ) ) {
			return;
		}
		var card    = e.target.closest( '.mulino-manager-card' );
		var docId   = e.target.getAttribute( 'data-doc-id' );
		var current = card.getAttribute( 'data-doc-name' ) || '';
		var name    = prompt( i18n.renameDocPrompt, current );
		if ( ! name || name === current ) {
			return;
		}
		postAjax( { action: 'mulino_rename_doc', doc_id: docId, name: name } ).then( function ( json ) {
			if ( json.success ) {
				card.setAttribute( 'data-doc-name', name );
				card.querySelector( '.mulino-name' ).textContent = name;
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotRename );
			}
		} );
	} );

	// --- Delete a document ---
	grid.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-delete' ) ) {
			return;
		}
		if ( ! confirm( i18n.deleteDocConfirm ) ) {
			return;
		}
		var docId = e.target.getAttribute( 'data-doc-id' );
		postAjax( { action: 'mulino_delete_doc', doc_id: docId } ).then( function ( json ) {
			if ( json.success ) {
				e.target.closest( '.mulino-manager-card' ).remove();
				if ( ! grid.querySelector( '.mulino-manager-card' ) && ! grid.querySelector( '.mulino-empty' ) ) {
					grid.innerHTML = '<p class="mulino-empty"></p>';
					grid.querySelector( '.mulino-empty' ).textContent = i18n.noDocuments;
				}
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotDelete );
			}
		} );
	} );

	// --- Drag existing folders in the tree to re-parent them, and use
	// the tree as drop targets for both documents and folders ---
	tree.addEventListener( 'dragstart', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-tree-row' ) ) {
			return;
		}
		var li = e.target.closest( '.mulino-tree-item' );
		if ( ! li || '0' === li.getAttribute( 'data-term-id' ) ) {
			return; // the "All" root row isn't a real, movable term
		}
		e.target.classList.add( 'is-dragging' );
		e.dataTransfer.setData( 'text/plain', 'folder:' + li.getAttribute( 'data-term-id' ) );
	} );
	tree.addEventListener( 'dragend', function ( e ) {
		if ( e.target.classList.contains( 'mulino-tree-row' ) ) {
			e.target.classList.remove( 'is-dragging' );
		}
	} );

	tree.querySelectorAll( '.mulino-tree-item' ).forEach( function ( li ) {
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
				postAjax( { action: 'mulino_move', doc_id: id, folder_id: targetFolder } ).then( function ( json ) {
					if ( json.success ) {
						var card = grid.querySelector( '[data-doc-id="' + id + '"]' );
						if ( card ) {
							card.remove();
						}
						if ( ! grid.querySelector( '.mulino-manager-card' ) && ! grid.querySelector( '.mulino-empty' ) ) {
							grid.innerHTML = '<p class="mulino-empty"></p>';
							grid.querySelector( '.mulino-empty' ).textContent = i18n.noDocuments;
						}
					} else {
						alert( json.data && json.data.message ? json.data.message : i18n.couldNotMoveDoc );
					}
				} );
			} else if ( 'folder' === kind ) {
				if ( id === targetFolder ) {
					return; // dropped a folder onto itself
				}
				postAjax( { action: 'mulino_move_folder', term_id: id, new_parent_id: targetFolder } ).then( function ( json ) {
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
	document.getElementById( 'mulino-new-folder' ).addEventListener( 'click', function () {
		var name = prompt( i18n.newFolderPrompt );
		if ( ! name ) {
			return;
		}
		var currentFolderId = dropzone.getAttribute( 'data-folder-id' ) || 0;
		postAjax( { action: 'mulino_create_folder', name: name, parent_id: currentFolderId } ).then( function ( json ) {
			if ( json.success ) {
				location.reload();
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotCreateFolder );
			}
		} );
	} );

	// --- Rename folder ---
	tree.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-tree-rename' ) ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var li      = e.target.closest( '.mulino-tree-item' );
		var link    = li.querySelector( ':scope > .mulino-tree-row > .mulino-tree-link' );
		var termId  = e.target.getAttribute( 'data-term-id' );
		var current = link.getAttribute( 'data-term-name' ) || link.textContent;
		var name    = prompt( i18n.renameFolderPrompt, current );
		if ( ! name || name === current ) {
			return;
		}
		postAjax( { action: 'mulino_rename_folder', term_id: termId, name: name } ).then( function ( json ) {
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
		if ( ! e.target.classList.contains( 'mulino-tree-delete' ) ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var termId = e.target.getAttribute( 'data-term-id' );
		if ( ! confirm( i18n.deleteFolderConfirm ) ) {
			return;
		}
		postAjax( { action: 'mulino_delete_folder', term_id: termId } ).then( function ( json ) {
			if ( json.success ) {
				var wasSelected = e.target.closest( '.mulino-tree-item' ).classList.contains( 'is-selected' );
				e.target.closest( '.mulino-tree-item' ).remove();
				if ( wasSelected ) {
					window.location.href = mulinoManager.rootUrl;
				}
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotDeleteFolder );
			}
		} );
	} );
} )();
