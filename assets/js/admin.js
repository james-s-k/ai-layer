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

    } );

} )();
