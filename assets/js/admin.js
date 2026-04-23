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

        // Auto-resize textareas to fit content.
        document.querySelectorAll( '.wpail-admin textarea, .wpail-meta-box textarea' ).forEach( function ( ta ) {
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

        // llms.txt page — show/hide custom pages textarea.
        var includePages = document.getElementById( 'wpail_llmstxt_include_pages' );
        var pagesRow     = document.getElementById( 'wpail_llmstxt_pages_row' );
        if ( includePages && pagesRow ) {
            includePages.addEventListener( 'change', function () {
                pagesRow.style.display = includePages.checked ? '' : 'none';
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
