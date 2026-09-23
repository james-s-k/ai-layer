/**
 * AI Layer — AI Import page JS
 */
( function () {
	'use strict';

	const config = window.wpailAiImport;
	if ( ! config ) {
		return;
	}

	const ajaxUrl          = config.ajaxUrl;
	const nonce            = config.nonce;
	const labels           = config.labels;
	const reviewUrls       = config.reviewUrls;
	const importPresets    = config.importPresets;
	const recommendedMax   = config.recommendedMax;
	const i18n             = config.i18n;

	// ── Page selection (presets, suggestions, search, sections) ───
	let selectedPages = [];
	const picker           = document.getElementById( 'wpail-import-picker' );
	const searchInput      = picker?.querySelector( '.wpail-page-picker__search' );
	const dropdown         = picker?.querySelector( '.wpail-page-picker__dropdown' );
	const selectedWrap     = document.getElementById( 'wpail-selected-pages' );
	const clearAllLink     = document.getElementById( 'wpail-clear-pages' );
	const selectedCountEl  = document.getElementById( 'wpail-selected-count' );
	const selectedEmptyEl  = document.getElementById( 'wpail-selected-empty' );
	const selectedWarnEl   = document.getElementById( 'wpail-selected-warn' );
	const suggestCheckboxes = document.querySelectorAll( '.wpail-suggest-cb' );
	const sectionSelect    = document.getElementById( 'wpail-section-parent' );
	const sectionAddBtn    = document.getElementById( 'wpail-section-add-btn' );
	const sectionHint      = document.getElementById( 'wpail-section-hint' );
	let searchCache = {};
	let debounce;

	function isSelected( id ) {
		return selectedPages.some( ( p ) => p.id === id );
	}

	function addPages( pages ) {
		pages.forEach( ( page ) => {
			const id = parseInt( page.id, 10 );
			if ( ! id || isSelected( id ) ) {
				return;
			}
			selectedPages.push( { id, title: page.title || '' } );
		} );
		syncUi();
	}

	function removePage( id ) {
		selectedPages = selectedPages.filter( ( p ) => p.id !== id );
		syncUi();
	}

	function syncSuggestionCheckboxes() {
		suggestCheckboxes.forEach( ( cb ) => {
			const id = parseInt( cb.value, 10 );
			cb.checked = isSelected( id );
		} );
	}

	function updateCountWarning() {
		const count = selectedPages.length;
		if ( selectedCountEl ) {
			selectedCountEl.textContent = '(' + count + ')';
		}
		if ( selectedEmptyEl ) {
			selectedEmptyEl.style.display = count > 0 ? 'none' : '';
		}
		if ( clearAllLink ) {
			clearAllLink.style.display = count > 0 ? '' : 'none';
		}
		if ( ! selectedWarnEl ) {
			return;
		}
		if ( count > recommendedMax ) {
			selectedWarnEl.style.display = '';
			selectedWarnEl.textContent = i18n.tooManyPagesWarning;
		} else {
			selectedWarnEl.style.display = 'none';
			selectedWarnEl.textContent = '';
		}
	}

	function renderChips() {
		if ( ! selectedWrap ) {
			return;
		}
		selectedWrap.innerHTML = '';
		selectedPages.forEach( ( page ) => {
			const chip = document.createElement( 'span' );
			chip.className = 'wpail-import-pages__chip';
			chip.textContent = page.title;
			const rm = document.createElement( 'button' );
			rm.type = 'button';
			rm.className = 'wpail-import-pages__chip-remove';
			rm.textContent = '×';
			rm.title = i18n.remove;
			rm.addEventListener( 'click', () => removePage( page.id ) );
			chip.appendChild( rm );
			selectedWrap.appendChild( chip );
		} );
		updateCountWarning();
	}

	function syncUi() {
		syncSuggestionCheckboxes();
		renderChips();
	}

	function collectCheckedSuggestions() {
		const pages = [];
		suggestCheckboxes.forEach( ( cb ) => {
			if ( ! cb.checked ) {
				return;
			}
			pages.push( {
				id: parseInt( cb.value, 10 ),
				title: cb.dataset.title || '',
			} );
		} );
		return pages;
	}

	function applySuggestionSelection() {
		const checkedIds = new Set(
			collectCheckedSuggestions().map( ( p ) => p.id )
		);
		selectedPages = selectedPages.filter( ( p ) => {
			const cb = document.querySelector( '.wpail-suggest-cb[value="' + p.id + '"]' );
			return ! cb || checkedIds.has( p.id );
		} );
		collectCheckedSuggestions().forEach( ( page ) => {
			if ( ! isSelected( page.id ) ) {
				selectedPages.push( page );
			}
		} );
		syncUi();
	}

	suggestCheckboxes.forEach( ( cb ) => {
		cb.addEventListener( 'change', applySuggestionSelection );
	} );

	document.getElementById( 'wpail-suggest-select-all' )?.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		suggestCheckboxes.forEach( ( cb ) => { cb.checked = true; } );
		applySuggestionSelection();
	} );

	document.getElementById( 'wpail-suggest-select-none' )?.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		suggestCheckboxes.forEach( ( cb ) => { cb.checked = false; } );
		applySuggestionSelection();
	} );

	document.querySelectorAll( '.wpail-import-preset-btn' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', function () {
			const preset = btn.dataset.preset;
			const pages = importPresets[ preset ] || [];
			if ( ! pages.length ) {
				alert( i18n.noPresetPages );
				return;
			}
			addPages( pages );
			suggestCheckboxes.forEach( ( cb ) => {
				const id = parseInt( cb.value, 10 );
				cb.checked = isSelected( id );
			} );
		} );
	} );

	clearAllLink?.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		selectedPages = [];
		suggestCheckboxes.forEach( ( cb ) => { cb.checked = false; } );
		syncUi();
	} );

	sectionSelect?.addEventListener( 'change', function () {
		const opt = sectionSelect.options[ sectionSelect.selectedIndex ];
		const childCount = parseInt( opt?.dataset.childCount || '0', 10 );
		if ( sectionAddBtn ) {
			sectionAddBtn.disabled = ! sectionSelect.value;
		}
		if ( ! sectionHint ) {
			return;
		}
		if ( ! sectionSelect.value ) {
			sectionHint.textContent = '';
			return;
		}
		sectionHint.textContent = childCount === 0
			? i18n.sectionAddsOne
			: i18n.sectionAddsSubtree;
	} );

	sectionAddBtn?.addEventListener( 'click', async function () {
		const parentId = parseInt( sectionSelect.value, 10 );
		if ( ! parentId ) {
			return;
		}
		sectionAddBtn.disabled = true;
		const body = new FormData();
		body.append( 'action', 'wpail_ai_pages_under_parent' );
		body.append( 'nonce', nonce );
		body.append( 'parent_id', String( parentId ) );
		try {
			const res = await fetch( ajaxUrl, { method: 'POST', body } );
			const json = await res.json();
			if ( ! json.success ) {
				alert( json.data?.message || i18n.sectionLoadFailed );
				return;
			}
			addPages( json.data.pages || [] );
		} catch ( err ) {
			alert( i18n.sectionLoadFailed );
		} finally {
			sectionAddBtn.disabled = ! sectionSelect.value;
		}
	} );

	function searchPages( term, callback ) {
		if ( searchCache[ term ] ) {
			callback( searchCache[ term ] );
			return;
		}
		const body = new FormData();
		body.append( 'action', 'wpail_ai_search_pages' );
		body.append( 'nonce', nonce );
		body.append( 'term', term );
		fetch( ajaxUrl, { method: 'POST', body } )
			.then( ( r ) => r.json() )
			.then( ( data ) => {
				const results = data.success && Array.isArray( data.data?.pages )
					? data.data.pages.map( ( item ) => ( {
						id: parseInt( item.id, 10 ),
						title: item.title || '',
					} ) )
					: [];
				searchCache[ term ] = results;
				callback( results );
			} )
			.catch( () => callback( [] ) );
	}

	function renderDropdown( results ) {
		if ( ! dropdown ) {
			return;
		}
		dropdown.innerHTML = '';
		if ( ! results.length ) {
			const empty = document.createElement( 'div' );
			empty.className = 'wpail-page-picker__option wpail-page-picker__option--empty';
			empty.textContent = i18n.noPagesFound;
			dropdown.appendChild( empty );
			dropdown.style.display = 'block';
			return;
		}
		results.forEach( ( page ) => {
			const opt = document.createElement( 'div' );
			opt.className = 'wpail-page-picker__option';
			opt.textContent = page.title;
			if ( isSelected( page.id ) ) {
				opt.style.opacity = '0.45';
				opt.style.cursor = 'default';
			}
			opt.addEventListener( 'mousedown', function ( e ) {
				e.preventDefault();
				if ( isSelected( page.id ) ) {
					return;
				}
				addPages( [ page ] );
				if ( searchInput ) {
					searchInput.value = '';
				}
				dropdown.style.display = 'none';
			} );
			dropdown.appendChild( opt );
		} );
		dropdown.style.display = 'block';
	}

	if ( searchInput && dropdown && picker ) {
		searchInput.addEventListener( 'input', function () {
			clearTimeout( debounce );
			const term = searchInput.value.trim();
			if ( term.length < 2 ) {
				dropdown.innerHTML = '';
				dropdown.style.display = 'none';
				return;
			}
			debounce = setTimeout( () => searchPages( term, renderDropdown ), 300 );
		} );

		searchInput.addEventListener( 'focus', function () {
			const term = searchInput.value.trim();
			if ( term.length >= 2 ) {
				searchPages( term, renderDropdown );
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! picker.contains( e.target ) ) {
				dropdown.style.display = 'none';
			}
		} );
	}

	// Initialise from pre-checked suggestions (wizard / default).
	if ( picker ) {
		applySuggestionSelection();
	}

	// ── Extraction ────────────────────────────────────────────────
	const btn         = document.getElementById( 'wpail-ai-start-btn' );
	const progress    = document.getElementById( 'wpail-ai-progress' );
	const bar         = document.getElementById( 'wpail-ai-bar' );
	const statusTxt   = document.getElementById( 'wpail-ai-status-text' );
	const results     = document.getElementById( 'wpail-ai-results' );
	const resultsBody = document.getElementById( 'wpail-ai-results-body' );
	const errorBox    = document.getElementById( 'wpail-ai-error' );
	const errorTxt    = document.getElementById( 'wpail-ai-error-text' );

	btn?.addEventListener( 'click', async function () {
		const checkedTypes = [ ...document.querySelectorAll( '.wpail-ai-type-cb:checked' ) ].map( ( cb ) => cb.value );

		if ( ! selectedPages.length ) {
			alert( i18n.selectPages );
			return;
		}
		if ( selectedPages.length > recommendedMax ) {
			if ( ! confirm( i18n.confirmManyPages ) ) {
				return;
			}
		}
		if ( ! checkedTypes.length ) {
			alert( i18n.selectTypes );
			return;
		}

		btn.disabled = true;
		errorBox.style.display = 'none';
		results.style.display  = 'none';
		resultsBody.innerHTML  = '';
		progress.style.display = 'block';
		bar.style.width        = '0';
		statusTxt.textContent  = i18n.preparing;

		const startData = new FormData();
		startData.append( 'action', 'wpail_ai_start' );
		startData.append( 'nonce', nonce );
		selectedPages.forEach( ( p ) => startData.append( 'post_ids[]', p.id ) );
		checkedTypes.forEach( ( t ) => startData.append( 'types[]', t ) );

		let jobId;
		let activeTypes;
		try {
			const startRes  = await fetch( ajaxUrl, { method: 'POST', body: startData } );
			const startJson = await startRes.json();
			if ( ! startJson.success ) {
				throw new Error( startJson.data?.message || 'Failed to start job.' );
			}
			jobId       = startJson.data.job_id;
			activeTypes = startJson.data.types;
		} catch ( err ) {
			showError( err.message );
			return;
		}

		for ( let i = 0; i < activeTypes.length; i++ ) {
			bar.style.width       = Math.round( ( i / activeTypes.length ) * 100 ) + '%';
			statusTxt.textContent = activeTypes[ i ] === 'link'
				? i18n.linkingRelationships
				: i18n.extracting + ' ' + ( labels[ activeTypes[ i ] ] || activeTypes[ i ] ) + '…';

			const stepData = new FormData();
			stepData.append( 'action', 'wpail_ai_run_step' );
			stepData.append( 'nonce', nonce );
			stepData.append( 'job_id', jobId );

			try {
				const stepRes  = await fetch( ajaxUrl, { method: 'POST', body: stepData } );
				const stepJson = await stepRes.json();
				if ( ! stepJson.success ) {
					throw new Error( stepJson.data?.message || 'Step failed.' );
				}

				const d = stepJson.data;
				if ( d.step_name ) {
					const tr = document.createElement( 'tr' );
					if ( d.step_name === 'link' ) {
						tr.innerHTML = '<td>' + labels.link + '</td><td><strong>' + d.created + '</strong></td><td>—</td>';
					} else {
						const skippedNote = d.skipped > 0
							? ' <span class="description">(' + d.skipped + ' ' + i18n.duplicatesSkipped + ')</span>'
							: '';
						const reviewLink = d.created > 0
							? '<a href="' + reviewUrls[ d.step_name ] + '">' + i18n.reviewDrafts + '</a>'
							: '—';
						tr.innerHTML = '<td>' + ( labels[ d.step_name ] || d.step_name ) + '</td>' +
							'<td><strong>' + d.created + '</strong>' + skippedNote + '</td>' +
							'<td>' + reviewLink + '</td>';
					}
					resultsBody.appendChild( tr );
				}
			} catch ( err ) {
				showError( err.message );
				return;
			}
		}

		bar.style.width = '100%';
		progress.style.display = 'none';
		results.style.display  = 'block';
		btn.disabled = false;
	} );

	function showError( msg ) {
		progress.style.display = 'none';
		errorTxt.textContent   = msg;
		errorBox.style.display = 'block';
		btn.disabled           = false;
	}

	// ── Relationship maintenance actions ──────────────────────────
	async function runAction( action, btnEl, statusEl, pendingMsg, successFn ) {
		btnEl.disabled         = true;
		statusEl.style.color = '#646970';
		statusEl.textContent = pendingMsg;
		const data = new FormData();
		data.append( 'action', action );
		data.append( 'nonce', nonce );
		try {
			const res  = await fetch( ajaxUrl, { method: 'POST', body: data } );
			const json = await res.json();
			if ( json.success ) {
				statusEl.style.color = '#00a32a';
				statusEl.textContent = successFn( json.data );
			} else {
				statusEl.style.color = '#d63638';
				statusEl.textContent = json.data?.message || i18n.somethingWrong;
			}
		} catch ( err ) {
			statusEl.style.color = '#d63638';
			statusEl.textContent = err.message;
		} finally {
			btnEl.disabled = false;
		}
	}

	document.getElementById( 'wpail-resync-btn' )?.addEventListener( 'click', function () {
		if ( ! confirm( i18n.confirmResync ) ) {
			return;
		}
		runAction(
			'wpail_ai_resync',
			this,
			document.getElementById( 'wpail-resync-status' ),
			i18n.resyncing,
			( data ) => i18n.donePrefix + ' ' + data.processed + ' ' + i18n.postsProcessed
		);
	} );

	document.getElementById( 'wpail-find-rel-btn' )?.addEventListener( 'click', function () {
		if ( ! confirm( i18n.confirmFindRelationships ) ) {
			return;
		}
		runAction(
			'wpail_ai_find_relationships',
			this,
			document.getElementById( 'wpail-find-rel-status' ),
			i18n.findingRelationships,
			( data ) => i18n.donePrefix + ' ' + data.updated + ' ' + i18n.entitiesUpdated
		);
	} );

	document.getElementById( 'wpail-rebuild-rel-btn' )?.addEventListener( 'click', function () {
		if ( ! confirm( i18n.confirmRebuildRelationships ) ) {
			return;
		}
		runAction(
			'wpail_ai_rebuild_relationships',
			this,
			document.getElementById( 'wpail-rebuild-rel-status' ),
			i18n.rebuildingRelationships,
			( data ) => i18n.donePrefix + ' ' + data.updated + ' ' + i18n.entitiesUpdated
		);
	} );
}() );
