/**
 * AI Layer — Admin JS
 *
 * Minimal JS for admin UX enhancements.
 * No heavy dependencies — plain JS with jQuery only where WordPress requires it.
 */

( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {

        // Collapse/expand field groups on header click.
        document.querySelectorAll( '.wpail-field-group h2' ).forEach( function ( heading ) {
            heading.style.cursor = 'pointer';

            heading.addEventListener( 'click', function () {
                var table = heading.nextElementSibling;
                if ( table ) {
                    var isHidden = table.style.display === 'none';
                    table.style.display = isHidden ? '' : 'none';
                    heading.style.opacity = isHidden ? '1' : '0.6';
                }
            } );
        } );

        // Meta box group titles — same collapse behaviour.
        document.querySelectorAll( '.wpail-meta-box__group-title' ).forEach( function ( title ) {
            title.style.cursor = 'pointer';

            title.addEventListener( 'click', function () {
                var next = title.nextElementSibling;
                if ( next ) {
                    var isHidden = next.style.display === 'none';
                    next.style.display = isHidden ? '' : 'none';
                    title.style.opacity = isHidden ? '1' : '0.6';
                }
            } );
        } );

        // Auto-resize textareas to fit content (exclude read-only preview areas).
        document.querySelectorAll( '.wpail-admin textarea:not(.wpail-llmstxt__preview-area), .wpail-meta-box textarea' ).forEach( function ( ta ) {
            ta.style.resize = 'vertical';
            ta.style.minHeight = '60px';
        } );

        // llms.txt page — copy preview to clipboard.
        var copyBtn = document.getElementById( 'wpail-llmstxt-copy' );
        if ( copyBtn ) {
            copyBtn.addEventListener( 'click', function () {
                var preview = document.getElementById( 'wpail-llmstxt-preview' );
                if ( ! preview ) { return; }
                navigator.clipboard.writeText( preview.value ).then( function () {
                    var original = copyBtn.textContent;
                    copyBtn.textContent = 'Copied!';
                    setTimeout( function () { copyBtn.textContent = original; }, 2000 );
                } );
            } );
        }

        // Settings page — show/hide FAQPage specific-page list based on radio.
        var faqPageList = document.getElementById( 'wpail_faq_page_list' );
        if ( faqPageList ) {
            document.querySelectorAll( 'input[name="schema_faq_pages_mode"]' ).forEach( function ( radio ) {
                radio.addEventListener( 'change', function () {
                    faqPageList.style.display = this.value === 'specific' ? '' : 'none';
                } );
            } );
        }

        // llms.txt page — show/hide key pages section.
        var includePages  = document.getElementById( 'wpail_llmstxt_include_pages' );
        var pagesSection  = document.getElementById( 'wpail_llmstxt_pages_section' );
        if ( includePages && pagesSection ) {
            includePages.addEventListener( 'change', function () {
                pagesSection.style.display = includePages.checked ? '' : 'none';
            } );
            initPagePickers( pagesSection );
            initCustomPageRepeater();
        }

        // ----------------------------------------------------------------
        // Page picker — searchable dropdown backed by WP REST API.
        // ----------------------------------------------------------------

        var pageSearchCache = {};

        function searchPages( term, callback ) {
            if ( pageSearchCache[ term ] ) {
                callback( pageSearchCache[ term ] );
                return;
            }
            var cfg    = window.wpailLlmsTxt || {};
            var nonce  = cfg.nonce  || '';
            var apiUrl = cfg.restUrl || '';
            var url    = apiUrl + 'wp/v2/search?search=' + encodeURIComponent( term ) + '&_fields=id,title&per_page=10';
            fetch( url, { headers: { 'X-WP-Nonce': nonce } } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( data ) {
                    var results = Array.isArray( data ) ? data.map( function ( item ) {
                        return { id: item.id, title: item.title };
                    } ) : [];
                    pageSearchCache[ term ] = results;
                    callback( results );
                } )
                .catch( function () { callback( [] ); } );
        }

        function renderDropdown( picker, results ) {
            var dropdown    = picker.querySelector( '.wpail-page-picker__dropdown' );
            var idInput     = picker.querySelector( '.wpail-page-picker__id' );
            var searchInput = picker.querySelector( '.wpail-page-picker__search' );
            var clearBtn    = picker.querySelector( '.wpail-page-picker__clear' );
            dropdown.innerHTML = '';
            if ( ! results.length ) {
                var empty = document.createElement( 'div' );
                empty.className   = 'wpail-page-picker__option wpail-page-picker__option--empty';
                empty.textContent = 'No pages found.';
                dropdown.appendChild( empty );
                dropdown.style.display = 'block';
                return;
            }
            results.forEach( function ( page ) {
                var opt = document.createElement( 'div' );
                opt.className   = 'wpail-page-picker__option';
                opt.textContent = page.title;
                opt.addEventListener( 'mousedown', function ( e ) {
                    e.preventDefault();
                    idInput.value     = page.id;
                    searchInput.value = page.title;
                    if ( clearBtn ) { clearBtn.style.display = 'inline'; }
                    dropdown.style.display = 'none';
                } );
                dropdown.appendChild( opt );
            } );
            dropdown.style.display = 'block';
        }

        function initSinglePicker( picker ) {
            if ( picker.dataset.pickerInit ) { return; }
            picker.dataset.pickerInit = '1';

            var searchInput = picker.querySelector( '.wpail-page-picker__search' );
            var idInput     = picker.querySelector( '.wpail-page-picker__id' );
            var dropdown    = picker.querySelector( '.wpail-page-picker__dropdown' );
            var clearBtn    = picker.querySelector( '.wpail-page-picker__clear' );
            if ( ! searchInput || ! idInput || ! dropdown ) { return; }

            var debounce;

            searchInput.addEventListener( 'input', function () {
                clearTimeout( debounce );
                var term = searchInput.value.trim();
                if ( term.length < 2 ) {
                    dropdown.innerHTML    = '';
                    dropdown.style.display = 'none';
                    return;
                }
                debounce = setTimeout( function () {
                    searchPages( term, function ( results ) { renderDropdown( picker, results ); } );
                }, 300 );
            } );

            searchInput.addEventListener( 'focus', function () {
                var term = searchInput.value.trim();
                if ( term.length >= 2 ) {
                    searchPages( term, function ( results ) { renderDropdown( picker, results ); } );
                }
            } );

            document.addEventListener( 'click', function ( e ) {
                if ( ! picker.contains( e.target ) ) {
                    dropdown.style.display = 'none';
                }
            } );

            if ( clearBtn ) {
                clearBtn.addEventListener( 'click', function () {
                    idInput.value          = '0';
                    searchInput.value      = '';
                    clearBtn.style.display = 'none';
                    dropdown.style.display = 'none';
                } );
            }
        }

        function initPagePickers( container ) {
            ( container || document ).querySelectorAll( '.wpail-page-picker' ).forEach( initSinglePicker );
        }

        function reindexRepeater() {
            var repeater = document.getElementById( 'wpail-page-repeater' );
            if ( ! repeater ) { return; }
            repeater.querySelectorAll( '.wpail-page-repeater__row' ).forEach( function ( row, i ) {
                var idInput = row.querySelector( '.wpail-page-picker__id' );
                if ( idInput ) { idInput.name = 'wpail_llmstxt[pages][custom][' + i + '][id]'; }
            } );
        }

        function initCustomPageRepeater() {
            var repeater = document.getElementById( 'wpail-page-repeater' );
            var addBtn   = document.getElementById( 'wpail-add-custom-page' );
            var template = document.getElementById( 'wpail-custom-page-template' );
            if ( ! repeater || ! addBtn || ! template ) { return; }

            function attachRowListeners( row ) {
                var removeBtn = row.querySelector( '.wpail-page-repeater__remove' );
                if ( removeBtn ) {
                    removeBtn.addEventListener( 'click', function () {
                        row.remove();
                        reindexRepeater();
                    } );
                }
                var picker = row.querySelector( '.wpail-page-picker' );
                if ( picker ) { initSinglePicker( picker ); }
            }

            repeater.querySelectorAll( '.wpail-page-repeater__row' ).forEach( attachRowListeners );

            addBtn.addEventListener( 'click', function () {
                var clone = template.content.cloneNode( true );
                repeater.appendChild( clone );
                var newRow = repeater.querySelector( '.wpail-page-repeater__row:last-child' );
                reindexRepeater();
                attachRowListeners( newRow );
                var input = newRow.querySelector( '.wpail-page-picker__search' );
                if ( input ) { input.focus(); }
            } );
        }

        // ----------------------------------------------------------------
        // AI.txt page — live preview + agent repeater.
        // ----------------------------------------------------------------

        var aiTxtPreview = document.getElementById( 'wpail-aitxt-preview' );
        if ( aiTxtPreview ) {
            initAiTxtPage();
        }

        function initAiTxtPage() {
            var form          = document.getElementById( 'wpail-aitxt-form' );
            var agentsContainer = document.getElementById( 'wpail-aitxt-agents' );
            var addAgentBtn   = document.getElementById( 'wpail-aitxt-add-agent' );
            var copyBtn       = document.getElementById( 'wpail-aitxt-copy' );
            var template      = document.getElementById( 'wpail-aitxt-agent-template' );

            // Counter starts after existing server-rendered rows.
            var rowCounter = ( window.wpailAiTxtAgentCount || 0 );

            function buildPreview() {
                var lines = [];
                var allowCrawling  = document.getElementById( 'wpail_aitxt_allow_crawling' ).checked;
                var allowTraining  = document.getElementById( 'wpail_aitxt_allow_training' ).checked;
                var requireAttr    = document.getElementById( 'wpail_aitxt_require_attribution' ).checked;

                lines.push( 'User-agent: *' );
                lines.push( allowCrawling ? 'Allow: /' : 'Disallow: /' );
                lines.push( '' );
                lines.push( allowTraining ? 'Training: allow' : 'Training: disallow' );
                if ( requireAttr ) {
                    lines.push( 'Attribution: required' );
                }

                agentsContainer.querySelectorAll( '.wpail-aitxt__agent-row' ).forEach( function ( row ) {
                    var nameInput  = row.querySelector( '.wpail-aitxt__agent-name' );
                    var allowInput = row.querySelector( '.wpail-aitxt__agent-allow' );
                    var trainInput = row.querySelector( '.wpail-aitxt__agent-training' );
                    var attrInput  = row.querySelector( '.wpail-aitxt__agent-attribution' );
                    if ( ! nameInput ) { return; }
                    var name = nameInput.value.trim();
                    if ( ! name ) { return; }
                    lines.push( '' );
                    lines.push( 'User-agent: ' + name );
                    lines.push( allowInput && allowInput.checked ? 'Allow: /' : 'Disallow: /' );
                    lines.push( trainInput && trainInput.checked ? 'Training: allow' : 'Training: disallow' );
                    if ( attrInput && attrInput.checked ) {
                        lines.push( 'Attribution: required' );
                    }
                } );

                aiTxtPreview.value = lines.join( '\n' ) + '\n';
            }

            function reindexAgentRows() {
                agentsContainer.querySelectorAll( '.wpail-aitxt__agent-row' ).forEach( function ( row, i ) {
                    var nameInput  = row.querySelector( '.wpail-aitxt__agent-name' );
                    var allowInput = row.querySelector( '.wpail-aitxt__agent-allow' );
                    var trainInput = row.querySelector( '.wpail-aitxt__agent-training' );
                    var attrInput  = row.querySelector( '.wpail-aitxt__agent-attribution' );
                    if ( nameInput )  { nameInput.name  = 'wpail_aitxt[agents][' + i + '][name]'; }
                    if ( allowInput ) { allowInput.name = 'wpail_aitxt[agents][' + i + '][allow]'; }
                    if ( trainInput ) { trainInput.name = 'wpail_aitxt[agents][' + i + '][allow_training]'; }
                    if ( attrInput )  { attrInput.name  = 'wpail_aitxt[agents][' + i + '][require_attribution]'; }
                } );
            }

            function attachRowListeners( row ) {
                row.querySelector( '.wpail-aitxt__agent-remove' ).addEventListener( 'click', function () {
                    row.remove();
                    reindexAgentRows();
                    buildPreview();
                } );
                row.querySelector( '.wpail-aitxt__agent-name' ).addEventListener( 'input', buildPreview );
                [ '.wpail-aitxt__agent-allow', '.wpail-aitxt__agent-training', '.wpail-aitxt__agent-attribution' ].forEach( function ( sel ) {
                    var el = row.querySelector( sel );
                    if ( el ) { el.addEventListener( 'change', buildPreview ); }
                } );
            }

            // Attach listeners to server-rendered rows.
            agentsContainer.querySelectorAll( '.wpail-aitxt__agent-row' ).forEach( attachRowListeners );

            // Add Agent button.
            if ( addAgentBtn && template ) {
                addAgentBtn.addEventListener( 'click', function () {
                    var idx  = rowCounter++;
                    var clone = template.content.cloneNode( true );
                    var row   = clone.querySelector( '.wpail-aitxt__agent-row' );
                    row.querySelector( '.wpail-aitxt__agent-name' ).name        = 'wpail_aitxt[agents][' + idx + '][name]';
                    row.querySelector( '.wpail-aitxt__agent-allow' ).name       = 'wpail_aitxt[agents][' + idx + '][allow]';
                    row.querySelector( '.wpail-aitxt__agent-training' ).name    = 'wpail_aitxt[agents][' + idx + '][allow_training]';
                    row.querySelector( '.wpail-aitxt__agent-attribution' ).name = 'wpail_aitxt[agents][' + idx + '][require_attribution]';
                    agentsContainer.appendChild( row );
                    attachRowListeners( row );
                    row.querySelector( '.wpail-aitxt__agent-name' ).focus();
                    buildPreview();
                } );
            }

            // Global toggle listeners.
            [ 'wpail_aitxt_allow_crawling', 'wpail_aitxt_allow_training', 'wpail_aitxt_require_attribution' ].forEach( function ( id ) {
                var el = document.getElementById( id );
                if ( el ) { el.addEventListener( 'change', buildPreview ); }
            } );

            // Copy to clipboard.
            if ( copyBtn ) {
                copyBtn.addEventListener( 'click', function () {
                    navigator.clipboard.writeText( aiTxtPreview.value ).then( function () {
                        var original = copyBtn.textContent;
                        copyBtn.textContent = 'Copied!';
                        setTimeout( function () { copyBtn.textContent = original; }, 2000 );
                    } );
                } );
            }

            // Initial render.
            buildPreview();
        }

    } );

} )();
