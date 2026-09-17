( function () {
	'use strict';

	var cfg = window.p20RaumfinderConfig || {};
	var root = document.querySelector( '[data-p20-rf-root]' );
	if ( ! root ) {
		return;
	}

	var STEP_LABELS = [
		'Personen',
		'Anlass',
		'Dauer',
		'Bestuhlung',
		'Technik',
		'Verpflegung',
	];

	var state = {
		view: 'loading', // loading | start | wizard | results | success
		step: 0,
		persons: 10,
		event_type: '',
		event_type_label: '',
		duration: '',
		duration_label: '',
		seating: '',
		seating_label: '',
		features: [],
		catering: [],
		remote: null,
		matchResult: null,
		modalRoom: null,
		requestRoom: null,
		submitting: false,
		submitError: '',
		loadError: '',
		resultsPage: 0,
		requestDraft: {},
	};

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			var val = attrs[ key ];
			if ( null === val || 'undefined' === typeof val ) {
				return;
			}
			if ( 'class' === key ) {
				node.className = val;
			} else if ( 'html' === key ) {
				node.innerHTML = val;
			} else if ( key.indexOf( 'on' ) === 0 && typeof val === 'function' ) {
				node.addEventListener( key.slice( 2 ), val );
			} else {
				node.setAttribute( key, val );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
			}
		} );
		return node;
	}

	function parseJsonSafely( r ) {
		return r.text().then( function ( text ) {
			var data = null;
			try {
				data = text ? JSON.parse( text ) : null;
			} catch ( e ) {
				data = null;
			}
			if ( ! r.ok || null === data ) {
				var err = new Error( 'p20_rf_http_' + r.status );
				err.status = r.status;
				err.data = data;
				throw err;
			}
			return data;
		} );
	}

	function apiGet( path ) {
		if ( ! cfg.restUrl ) {
			return Promise.reject( new Error( 'p20_rf_no_config' ) );
		}
		return fetch( cfg.restUrl + path, {
			headers: { 'X-WP-Nonce': cfg.nonce },
		} ).then( parseJsonSafely );
	}

	function apiPost( path, body ) {
		if ( ! cfg.restUrl ) {
			return Promise.reject( new Error( 'p20_rf_no_config' ) );
		}
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body ),
		} ).then( function ( r ) {
			return r.text().then( function ( text ) {
				var data = null;
				try {
					data = text ? JSON.parse( text ) : null;
				} catch ( e ) {
					data = null;
				}
				return { ok: r.ok, data: data };
			} );
		} );
	}

	var MIN_LOADER_MS = 2600;
	var MIN_LOADER_MS_SHORT = 1500;

	function withMinDelay( promise, ms ) {
		var wait = new Promise( function ( resolve ) {
			setTimeout( resolve, ms );
		} );
		return Promise.all( [ promise, wait ] ).then( function ( results ) {
			return results[ 0 ];
		} );
	}

	var LOGO_SVG_MARKUP = '<svg class="p20rfl-svg" viewBox="0 0 283.46 113.39" xmlns="http://www.w3.org/2000/svg">' +
		'<defs>' +
		'<linearGradient id="p20rflSteelGrad" x1="0" y1="0" x2="0" y2="1">' +
		'<stop offset="0%" stop-color="#e2e6e8"/><stop offset="14%" stop-color="#b7bfc4"/><stop offset="38%" stop-color="#828b91"/><stop offset="62%" stop-color="#565e63"/><stop offset="100%" stop-color="#2b3033"/>' +
		'</linearGradient>' +
		'<clipPath id="p20rflClip"><rect class="p20rfl-clipshape" x="11.56" y="24.85" width="260.35" height="63.68"/></clipPath>' +
		'</defs>' +
		'<g class="p20rfl-tagline">' +
		'<path class="p20rfl-fill" d="M95.37,59.51v-11.98h4.09c3.72,0,6.14,2.6,6.14,6s-2.42,5.98-6.14,5.98h-4.09ZM96.86,58.18h2.6c2.91,0,4.6-2.08,4.6-4.65s-1.63-4.67-4.6-4.67h-2.6v9.32Z"/>' +
		'<path class="p20rfl-fill" d="M107.77,59.51v-11.98h1.49v11.98h-1.49Z"/>' +
		'<path class="p20rfl-fill" d="M111.9,59.51v-11.98h7.85v1.33h-6.36v3.86h6.23v1.33h-6.23v4.13h6.36v1.33h-7.85Z"/>' +
		'<path class="p20rfl-fill" d="M135.01,59.51l-1.06-2.66h-6.07l-1.06,2.66h-1.65l4.81-11.98h1.85l4.83,11.98h-1.65ZM130.92,48.86l-2.6,6.66h5.19l-2.59-6.66Z"/>' +
		'<path class="p20rfl-fill" d="M138.06,59.51v-11.98h1.49v10.65h5.57v1.33h-7.06Z"/>' +
		'<path class="p20rfl-fill" d="M148.23,59.51v-10.65h-3.79v-1.33h9.09v1.33h-3.81v10.65h-1.49Z"/>' +
		'<path class="p20rfl-fill" d="M155.33,59.51v-11.98h7.85v1.33h-6.36v3.86h6.23v1.33h-6.23v4.13h6.36v1.33h-7.85Z"/>' +
		'</g>' +
		'<g class="p20rfl-clipped">' +
		'<g class="p20rfl-companyname">' +
		'<path class="p20rfl-fill" d="M94.84,85.07l-.09-3.37.29-.04c.94,2.56,2.94,4.53,5.93,4.53,1.55,0,2.99-.99,2.99-2.83,0-2.2-2.9-3.23-4.69-3.95-2.31-.96-4.24-1.73-4.24-4.33,0-2.78,2.24-4.51,5.77-4.51,1.66,0,3.14.36,4.31.94l.09,2.92-.25.09c-.92-2.31-2.83-3.57-5.01-3.57-1.8,0-3.21,1.05-3.21,2.83,0,2,2.27,2.81,4.06,3.52,2.29.88,5.01,1.89,5.01,4.58,0,3.08-2.83,4.78-5.41,4.78-2.31,0-4.4-.74-5.54-1.59Z"/>' +
		'<path class="p20rfl-fill" d="M107.75,81.25c0-3.77,2.92-6.2,6.06-6.2,2.4,0,4.04.65,4.04,1.28,0,.7-.85,1.46-1.53,1.46-.63,0-1.64-.7-3.37-2.36-1.95.29-3.21,2.22-3.21,4.38,0,2.94,1.8,5.03,4.65,5.03,1.66,0,2.94-.72,3.88-1.77l.2.22c-1.03,1.57-2.81,3.37-5.41,3.37-2.76,0-5.32-1.93-5.32-5.41Z"/>' +
		'<path class="p20rfl-fill" d="M119.55,86.12c.74-.25.9-.92.9-2.54v-11.9c0-1.32-.34-1.55-.9-1.79v-.31l2.96-1.3.22.18v8.62c1.23-1.01,2.85-2.02,4.6-2.02,2.06,0,3.46,1.08,3.46,3.7v4.83c0,1.62.16,2.29.9,2.54v.31h-4.08v-.31c.74-.25.9-.92.9-2.54v-4.42c0-2.02-.88-3.21-2.54-3.21-1.05,0-2.02.52-3.23,1.5v6.13c0,1.62.16,2.29.9,2.54v.31h-4.09v-.31Z"/>' +
		'<path class="p20rfl-fill" d="M133.42,86.12c.74-.25.9-.92.9-2.54v-5.16c0-1.32-.34-1.55-.9-1.8v-.31l2.96-1.3.22.18v2.72c.34-.79.92-1.48,1.64-1.98.83-.58,1.68-.88,2.76-.88.76,0,1.44.2,1.44.67,0,.49-.49,1.46-1.53,1.46-.63,0-1.48-.22-2.92-.67-.56.47-1.32,1.48-1.39,2.6v4.47c0,1.62.16,2.29.9,2.54v.31h-4.08v-.31Z"/>' +
		'<path class="p20rfl-fill" d="M150.5,85.54v-3.7c-.63,2.6-2.24,4.83-4.58,4.83-1.66,0-3.05-1.05-3.05-2.94,0-2.31,2.36-3.21,5.14-4l2.49-.72c-.04-2.22-1.26-3.08-2.92-3.08-1.26,0-2.49.54-3.64,1.91l-.22-.2c1.26-1.55,2.99-2.58,5.03-2.58,2.72,0,4,1.46,4,4.2v4.33c0,1.62.16,2.29.9,2.54v.31h-2.25c-.58,0-.9-.31-.9-.9ZM147.04,85.02c2.2,0,3.43-3.1,3.46-5.12v-.58l-2.4.7c-1.06.29-3.14,1.06-3.14,2.92,0,1.48,1.05,2.09,2.09,2.09Z"/>' +
		'<path class="p20rfl-fill" d="M156.09,82.47v-4.04c0-1.32-.34-1.55-.9-1.8v-.31l2.96-1.3.22.18v6.98c0,2.4.38,3.59,2.11,3.59,1.01,0,2.11-.61,3.21-1.71v-5.63c0-1.32-.34-1.55-.9-1.8v-.31l2.96-1.3.22.18v8.39c0,1.62.16,2.29.9,2.54v.31h-2.29c-.58,0-.9-.31-.9-.9v-1.01c-1.28,1.23-2.83,2.13-4.24,2.13-2.38,0-3.37-1.46-3.37-4.2Z"/>' +
		'<path class="p20rfl-fill" d="M170.83,84.98l-.92,1.46h-.25v-14.75c0-1.32-.34-1.55-.9-1.79v-.31l2.96-1.3.22.18v7.92c1.12-.72,2.45-1.32,3.73-1.32,3.19,0,5.12,2.63,5.12,5.41,0,3.43-2.36,6.17-6.08,6.17-1.62,0-3.05-.72-3.88-1.66ZM175.59,86.17c2.38,0,3.01-1.95,3.01-4s-.94-6.22-4.29-6.22c-.83,0-1.62.31-2.36.79v5.75c0,1.75,1.93,3.68,3.64,3.68Z"/>' +
		'<path class="p20rfl-fill" d="M182.86,81.25c0-3.82,2.98-6.2,5.9-6.2s4.65,2.09,4.65,4.04h-8.51c-.02.25-.04.47-.04.72,0,2.94,1.8,5.03,4.65,5.03,1.66,0,2.94-.72,3.88-1.77l.2.22c-1.03,1.57-2.81,3.37-5.41,3.37s-5.32-1.77-5.32-5.41ZM191.14,78.78c-.29-1.84-1.53-3.3-3.08-3.32-1.91-.02-2.87,1.57-3.14,3.32h6.22Z"/>' +
		'<path class="p20rfl-fill" d="M195.43,86.12c.74-.25.9-.92.9-2.54v-5.16c0-1.32-.34-1.55-.9-1.8v-.31l2.96-1.3.22.18v1.89c1.23-1.01,2.85-2.02,4.6-2.02,2.07,0,3.46,1.08,3.46,3.7v4.83c0,1.62.16,2.29.9,2.54v.31h-4.08v-.31c.74-.25.9-.92.9-2.54v-4.42c0-2.02-.88-3.21-2.54-3.21-1.05,0-2.02.52-3.23,1.5v6.13c0,1.62.16,2.29.9,2.54v.31h-4.08v-.31Z"/>' +
		'<path class="p20rfl-fill" d="M209.48,86.12c.74-.25.9-.92.9-2.54v-7.72h-1.3l.11-.36h1.19c.16-3.3,3.59-6.6,7.36-6.6.94,0,1.59.18,1.59.63s-.67,1.5-1.68,1.5c-1.14,0-2.13-.29-3.52-.83-.63.34-1.46.94-1.46,3.25v2.04h2.81l-.11.36h-2.69v7.72c0,1.62.16,2.29.9,2.54v.31h-4.09v-.31Z"/>' +
		'<path class="p20rfl-fill" d="M224.29,85.54v-3.7c-.63,2.6-2.24,4.83-4.58,4.83-1.66,0-3.05-1.05-3.05-2.94,0-2.31,2.36-3.21,5.14-4l2.49-.72c-.04-2.22-1.26-3.08-2.92-3.08-1.26,0-2.49.54-3.64,1.91l-.22-.2c1.26-1.55,2.99-2.58,5.03-2.58,2.72,0,4,1.46,4,4.2v4.33c0,1.62.16,2.29.9,2.54v.31h-2.24c-.58,0-.9-.31-.9-.9ZM220.84,85.02c2.2,0,3.43-3.1,3.46-5.12v-.58l-2.4.7c-1.05.29-3.14,1.06-3.14,2.92,0,1.48,1.05,2.09,2.09,2.09Z"/>' +
		'<path class="p20rfl-fill" d="M231.16,84.98l-.92,1.46h-.25v-14.75c0-1.32-.34-1.55-.9-1.79v-.31l2.96-1.3.22.18v7.92c1.12-.72,2.45-1.32,3.73-1.32,3.19,0,5.12,2.63,5.12,5.41,0,3.43-2.36,6.17-6.08,6.17-1.62,0-3.05-.72-3.88-1.66ZM235.92,86.17c2.38,0,3.01-1.95,3.01-4s-.94-6.22-4.29-6.22c-.83,0-1.62.31-2.36.79v5.75c0,1.75,1.93,3.68,3.64,3.68Z"/>' +
		'<path class="p20rfl-fill" d="M243.35,86.12c.74-.25.9-.92.9-2.54v-5.16c0-1.32-.34-1.55-.9-1.8v-.31l2.96-1.3.22.18v2.72c.34-.79.92-1.48,1.64-1.98.83-.58,1.68-.88,2.76-.88.76,0,1.44.2,1.44.67,0,.49-.49,1.46-1.53,1.46-.63,0-1.48-.22-2.92-.67-.56.47-1.32,1.48-1.39,2.6v4.47c0,1.62.16,2.29.9,2.54v.31h-4.08v-.31Z"/>' +
		'<path class="p20rfl-fill" d="M254.14,86.12c.74-.25.9-.92.9-2.54v-5.16c0-1.32-.34-1.55-.9-1.8v-.31l2.96-1.3.22.18v8.39c0,1.62.16,2.29.9,2.54v.31h-4.08v-.31ZM254.52,72.72c0-.88.67-1.55,1.53-1.55.9,0,1.53.67,1.53,1.55s-.67,1.53-1.53,1.53-1.53-.67-1.53-1.53Z"/>' +
		'<path class="p20rfl-fill" d="M260.24,86.12c.74-.25.9-.92.9-2.54v-11.9c0-1.32-.34-1.55-.9-1.79v-.31l2.96-1.3.22.18v12.91l3.52-3.5c1.44-1.44,1.41-2.29.96-2.45v-.2h2.85v.31c-.72.22-1.59.56-2.74,1.71l-1.44,1.44,2.67,4.83c.88,1.57,1.57,2.63,2.65,2.63v.31h-1.79c-1.1,0-2.13-.67-3.21-2.69l-1.86-3.55-1.62,1.62v1.77c0,1.62.16,2.29.9,2.54v.31h-4.08v-.31Z"/>' +
		'</g>' +
		'<g class="p20rfl-bars">' +
		'<path class="p20rfl-fill p20rfl-bar" style="animation-delay:.55s" d="M62.93,56.54v5.98h3.68v-5.98c0-1.02-.82-1.84-1.84-1.84s-1.84.83-1.84,1.84"/>' +
		'<path class="p20rfl-fill p20rfl-bar p20rfl-core" style="animation-delay:.05s" d="M68.64,38.17v-1.73h7.37v1.73c-1.02,0-1.84.83-1.84,1.84,0,.04,0,.08,0,.11h0v46.06h-3.68v-46.06h0s0-.07,0-.11c0-1.02-.83-1.84-1.84-1.84"/>' +
		'<path class="p20rfl-fill p20rfl-bar p20rfl-core" style="animation-delay:0s" d="M53.9,26.59v-1.73h7.37v1.73c-1.02,0-1.84.83-1.84,1.84,0,.04,0,.07,0,.11h0v57.64h-3.68V28.54h0s0-.07,0-.11c0-1.02-.83-1.84-1.84-1.84"/>' +
		'<path class="p20rfl-fill p20rfl-bar" style="animation-delay:.15s" d="M41.01,29.39h0s0-.09,0-.14c0-1.25-.83-2.27-1.84-2.27v-2.13h7.37v2.13c-1.02,0-1.84,1.02-1.84,2.27,0,.05,0,.09,0,.14h0v33.1h-3.68V29.39Z"/>' +
		'<path class="p20rfl-fill p20rfl-bar" style="animation-delay:.3s" d="M26.27,42.03h0s0-.1,0-.15c0-1.36-.83-2.47-1.84-2.47v-2.31h7.37v2.31c-1.02,0-1.84,1.1-1.84,2.47,0,.05,0,.1,0,.15h0v20.47h-3.68v-20.47Z"/>' +
		'<path class="p20rfl-fill p20rfl-bar" style="animation-delay:.4s" d="M52.3,31.61c0-1.02-.83-1.84-1.84-1.84s-1.84.83-1.84,1.84v5.98h3.68v-5.98Z"/>' +
		'<path class="p20rfl-fill p20rfl-bar" style="animation-delay:.6s" d="M66.62,44.08c0-1.02-.83-1.84-1.84-1.84s-1.84.83-1.84,1.84v5.98h3.68v-5.98Z"/>' +
		'<path class="p20rfl-fill p20rfl-bar" style="animation-delay:.5s" d="M48.62,44.08v5.98h3.68v-5.98c0-1.02-.82-1.84-1.84-1.84s-1.84.83-1.84,1.84"/>' +
		'<path class="p20rfl-fill p20rfl-bar" style="animation-delay:.45s" d="M37.02,44.08c0-1.02-.83-1.84-1.84-1.84s-1.84.83-1.84,1.84v5.98h3.68v-5.98Z"/>' +
		'</g>' +
		'<g class="p20rfl-mark">' +
		'<path class="p20rfl-fill" d="M12.75,85.82c.84-.28,1.02-1.04,1.02-2.87v-11.34c0-1.83-.18-2.59-1.02-2.87v-.36h5.34c5.8,0,7.12,2.87,7.12,4.93,0,3.18-2.29,5.88-7.5,5.88h-1.25v3.76c0,1.83.18,2.59,1.02,2.87v.36h-4.73v-.36ZM22.34,73.97c0-2.19-1.07-5.19-5.09-5.19-.25,0-.61.03-.79.05v9.95h1.12c3.33,0,4.76-1.93,4.76-4.81Z"/>' +
		'<path class="p20rfl-fill" d="M32.34,73.08c0-1.65-.79-3.26-3.03-3.26-1.25,0-2.39.48-3.48,1.68l-.23-.23c1.58-1.98,3.26-3.08,5.52-3.08s4.12,1.5,4.15,4.22c.03,4.5-5.57,8.93-8.8,11.7h5.6c2.52,0,3.03-.25,3.48-1.4l.28.03-.38,3.43h-11.4v-.36c3.51-3.38,8.29-8.39,8.29-12.74Z"/>' +
		'<path class="p20rfl-fill" d="M36.05,77.2c0-5.67,4.02-9,7.83-9,3.54,0,7.89,2.92,7.89,9.23,0,5.67-4.02,9-7.83,9-3.54,0-7.89-2.92-7.89-9.23ZM49.15,78.98c0-4.96-2.34-10.25-6.31-10.25-2.59,0-4.17,2.77-4.17,6.94,0,4.93,2.37,10.22,6.31,10.22,2.62,0,4.17-2.75,4.17-6.92Z"/>' +
		'</g>' +
		'</g>' +
		'</svg>';

	function renderLogoLoader() {
		var panel = el( 'div', { class: 'p20rfl-panel' } );
		var wrap = el( 'div', { class: 'p20rfl-wrap is-active', html: LOGO_SVG_MARKUP } );
		panel.appendChild( wrap );
		return panel;
	}

	var SVG_OPEN = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
	var SVG_CLOSE = '</svg>';

	var ICONS = {
		// Meeting — two people
		meeting: SVG_OPEN + '<circle cx="8.5" cy="7.5" r="2.5"/><circle cx="16" cy="9" r="2"/><path d="M3.5,20 C3.5,15.5 6,13.5 8.5,13.5 C11,13.5 13.5,15.5 13.5,20"/><path d="M14.5,20 C14.5,16.7 16,15 18,15 C20,15 21.5,16.7 21.5,20"/>' + SVG_CLOSE,
		// Besprechung — speech bubble
		besprechung: SVG_OPEN + '<path d="M4,5.5 H20 V16 H10.5 L6.5,19.5 V16 H4 Z"/><path d="M8,9.5 H16 M8,12.5 H13"/>' + SVG_CLOSE,
		// Seminar — presentation board
		seminar: SVG_OPEN + '<rect x="3" y="4.5" width="18" height="12" rx="1"/><path d="M7,20 H17 M12,16.5 V20 M7,8.5 H13 M7,11.5 H16"/>' + SVG_CLOSE,
		// Schulung — graduation cap
		schulung: SVG_OPEN + '<path d="M2,9.5 L12,5 L22,9.5 L12,14 L2,9.5 Z"/><path d="M6,11.5 V16 C6,17.5 8.5,19 12,19 C15.5,19 18,17.5 18,16 V11.5"/><path d="M22,9.5 V15.5"/>' + SVG_CLOSE,
		// Workshop — wrench
		workshop: SVG_OPEN + '<path d="M14.5,6.5 A4,4 0 1 1 9.9,11.1 L4,17 L3,20 L6,19 L11.9,13.1 A4,4 0 0 1 14.5,6.5 Z"/>' + SVG_CLOSE,
		// Vortrag — microphone
		vortrag: SVG_OPEN + '<rect x="9.5" y="3" width="5" height="10" rx="2.5"/><path d="M6,11 C6,15 8.7,17.5 12,17.5 C15.3,17.5 18,15 18,11 M12,17.5 V21 M8.5,21 H15.5"/>' + SVG_CLOSE,
		// Tagung — building
		tagung: SVG_OPEN + '<rect x="4" y="3" width="11" height="18"/><rect x="15" y="9" width="5" height="12"/><path d="M7,6.5 H8.5 M11,6.5 H12.5 M7,10 H8.5 M11,10 H12.5 M7,13.5 H8.5 M11,13.5 H12.5 M17,12.5 H18 M17,15.5 H18"/>' + SVG_CLOSE,
		// Konferenz — round table with people
		konferenz: SVG_OPEN + '<circle cx="12" cy="12" r="5"/><circle cx="12" cy="3.7" r="1.6"/><circle cx="19.3" cy="8.6" r="1.6"/><circle cx="19.3" cy="15.4" r="1.6"/><circle cx="12" cy="20.3" r="1.6"/><circle cx="4.7" cy="15.4" r="1.6"/><circle cx="4.7" cy="8.6" r="1.6"/>' + SVG_CLOSE,
		// Coaching — target
		coaching: SVG_OPEN + '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.8"/><circle cx="12" cy="12" r="1.2" fill="currentColor"/>' + SVG_CLOSE,
		// Event — star
		event: SVG_OPEN + '<path d="M12,3 L14.6,9.3 L21.5,9.8 L16.2,14.2 L18,21 L12,17.2 L6,21 L7.8,14.2 L2.5,9.8 L9.4,9.3 Z"/>' + SVG_CLOSE,
		// Hybrides Meeting — screen + signal
		'hybrides-meeting': SVG_OPEN + '<rect x="2.5" y="5" width="15" height="10.5" rx="1"/><path d="M7,19.5 H13" /><path d="M18,8 C19.5,9.5 19.5,12 18,13.5 M20.3,5.7 C22.9,8.3 22.9,13.2 20.3,15.8"/>' + SVG_CLOSE,
		// Sonstiges / fallback — plus
		sonstiges: SVG_OPEN + '<circle cx="12" cy="12" r="9"/><path d="M12,8 V16 M8,12 H16"/>' + SVG_CLOSE,
	};

	function eventIcon( slug ) {
		return ICONS[ slug ] || ICONS.sonstiges;
	}

	function seatingPictogram( key ) {
		var patterns = {
			theater: [ 1, 1, 1, 1, 1, 1, 1, 1, 1 ],
			parlament: [ 1, 0, 1, 1, 0, 1, 1, 0, 1 ],
			u_form: [ 1, 1, 1, 1, 0, 1, 1, 1, 1 ],
			block: [ 1, 1, 1, 0, 0, 0, 1, 1, 1 ],
			stuhlkreis: [ 0, 1, 0, 1, 0, 1, 0, 1, 0 ],
			individuell: [ 1, 0, 0, 0, 1, 0, 0, 0, 1 ],
		};
		var p = patterns[ key ] || patterns.theater;
		var wrap = el( 'span', { class: 'p20-rf__pictogram' } );
		p.forEach( function ( on ) {
			wrap.appendChild( el( 'span', { style: on ? '' : 'opacity:0' } ) );
		} );
		return wrap;
	}

	function render() {
		root.innerHTML = '';
		if ( 'loading' === state.view ) {
			root.appendChild( renderLogoLoader() );
			return;
		}
		if ( 'error' === state.view ) {
			root.appendChild( renderError() );
			return;
		}
		if ( 'start' === state.view ) {
			root.appendChild( renderStart() );
		} else if ( 'wizard' === state.view ) {
			root.appendChild( renderWizard() );
		} else if ( 'results' === state.view ) {
			root.appendChild( renderResults() );
		}
		if ( state.modalRoom ) {
			root.appendChild( renderModal() );
		}
		window.scrollTo( { top: root.getBoundingClientRect().top + window.scrollY - 40, behavior: 'smooth' } );
	}

	function renderError() {
		return el( 'div', { class: 'p20-rf__empty' }, [
			el( 'span', { class: 'p20-rf__kicker' }, [ 'Kurze Verzögerung' ] ),
			el( 'h2', { class: 'p20-rf__headline' }, [ 'Der Raumfinder konnte nicht geladen werden.' ] ),
			el( 'p', { class: 'p20-rf__subline', style: 'margin-left:auto;margin-right:auto' }, [ state.loadError || 'Bitte versuchen Sie es erneut, oder kontaktieren Sie uns direkt, falls das Problem bestehen bleibt.' ] ),
			el( 'button', { class: 'p20-rf-btn p20-rf-btn--primary', onclick: function () {
				state.view = 'loading';
				state.loadError = '';
				render();
				init();
			} }, [ 'Erneut versuchen' ] ),
		] );
	}

	function renderStart() {
		var s = ( state.remote && state.remote.settings ) || {};
		return el( 'div', { class: 'p20-rf__start' }, [
			el( 'span', { class: 'p20-rf__kicker' }, [ 'Raumfinder' ] ),
			el( 'h2', { class: 'p20-rf__headline' }, [ s.intro_headline || 'Welcher Raum passt zu Ihrer Veranstaltung?' ] ),
			el( 'p', { class: 'p20-rf__subline' }, [ s.intro_subline || 'Ein paar Angaben genuegen – wir zeigen Ihnen Raeume, die zu Ihren Plaenen passen.' ] ),
			el( 'button', {
				class: 'p20-rf-btn p20-rf-btn--primary',
				onclick: function () {
					state.view = 'wizard';
					state.step = 0;
					render();
				},
			}, [ 'Raum finden →' ] ),
			el( 'span', { class: 'p20-rf__hint' }, [ 'Dauert weniger als eine Minute.' ] ),
		] );
	}

	function progress() {
		var pct = Math.round( ( state.step / ( STEP_LABELS.length - 1 ) ) * 100 );
		var track = el( 'div', { class: 'p20-rf__progress' }, [
			el( 'div', { class: 'p20-rf__progress-mobile' }, [ 'Schritt ' + ( state.step + 1 ) + ' von ' + STEP_LABELS.length ] ),
			el( 'div', { class: 'p20-rf__progress-track' }, [ el( 'div', { class: 'p20-rf__progress-bar', style: 'width:' + pct + '%' } ) ] ),
			el( 'div', { class: 'p20-rf__progress-steps' }, STEP_LABELS.map( function ( label, i ) {
				return el( 'span', { class: i <= state.step ? 'is-active' : '' }, [ ( i + 1 ) + ' — ' + label ] );
			} ) ),
		] );
		return track;
	}

	function nav( canNext, onNext, nextLabel ) {
		var backBtn = el( 'button', {
			class: 'p20-rf-btn p20-rf-btn--ghost',
			onclick: function () {
				if ( 0 === state.step ) {
					state.view = 'start';
				} else {
					state.step -= 1;
				}
				render();
			},
		}, [ '← Zurück' ] );

		var nextBtn = el( 'button', {
			class: 'p20-rf-btn p20-rf-btn--primary',
			disabled: canNext ? null : 'disabled',
			onclick: onNext,
		}, [ nextLabel || 'Weiter →' ] );

		return el( 'div', { class: 'p20-rf__nav' }, [ backBtn, el( 'div', { class: 'p20-rf__nav-spacer' } ), nextBtn ] );
	}

	function goNext() {
		if ( state.step < STEP_LABELS.length - 1 ) {
			state.step += 1;
			render();
		} else {
			runMatch();
		}
	}

	function renderWizard() {
		var wrap = el( 'div', { class: 'p20-rf__panel' } );
		wrap.appendChild( progress() );
		var stepEl = el( 'div', { class: 'p20-rf__step' } );

		if ( 0 === state.step ) {
			stepEl.appendChild( stepPersons() );
		} else if ( 1 === state.step ) {
			stepEl.appendChild( stepEventType() );
		} else if ( 2 === state.step ) {
			stepEl.appendChild( stepDuration() );
		} else if ( 3 === state.step ) {
			stepEl.appendChild( stepSeating() );
		} else if ( 4 === state.step ) {
			stepEl.appendChild( stepFeatures() );
		} else if ( 5 === state.step ) {
			stepEl.appendChild( stepCatering() );
		}
		wrap.appendChild( stepEl );
		return wrap;
	}

	function stepPersons() {
		var frag = el( 'div', {}, [
			el( 'h3', { class: 'p20-rf__step-title' }, [ 'Mit wie vielen Personen planen Sie?' ] ),
			el( 'p', { class: 'p20-rf__step-sub' }, [ 'Die Anzahl Ihrer Gäste ist die wichtigste Grundlage für die passende Raumauswahl.' ] ),
		] );

		var value = el( 'input', {
			type: 'number',
			inputmode: 'numeric',
			min: '1',
			step: '1',
			class: 'p20-rf__stepper-value',
			value: String( state.persons ),
			'aria-label': 'Personenzahl',
		} );

		var navEl = nav( state.persons > 0, goNext );
		var nextBtn = navEl.querySelector( '.p20-rf-btn--primary' );

		function setPersons( n ) {
			state.persons = n;
			value.value = n > 0 ? String( n ) : '';
			if ( nextBtn ) {
				nextBtn.disabled = ! ( n > 0 );
			}
			syncQuick();
		}

		value.addEventListener( 'input', function () {
			var v = parseInt( value.value, 10 );
			state.persons = ( ! isNaN( v ) && v > 0 ) ? v : 0;
			if ( nextBtn ) {
				nextBtn.disabled = ! ( state.persons > 0 );
			}
			syncQuick();
		} );
		value.addEventListener( 'blur', function () {
			if ( ! ( state.persons > 0 ) ) {
				setPersons( 1 );
			}
		} );

		var minus = el( 'button', { class: 'p20-rf__stepper-btn', type: 'button', 'aria-label': 'Weniger', onclick: function () {
			setPersons( Math.max( 1, ( state.persons || 1 ) - 1 ) );
		} }, [ '−' ] );

		var plus = el( 'button', { class: 'p20-rf__stepper-btn', type: 'button', 'aria-label': 'Mehr', onclick: function () {
			setPersons( ( state.persons || 0 ) + 1 );
		} }, [ '+' ] );

		frag.appendChild( el( 'div', { class: 'p20-rf__stepper' }, [ minus, value, plus ] ) );
		frag.appendChild( el( 'p', { class: 'p20-rf__hint', style: 'margin:-8px 0 20px' }, [ 'Sie können die Personenzahl auch direkt eingeben, z. B. 12, 45 oder 69.' ] ) );

		var quickWrap = el( 'div', { class: 'p20-rf__quick-values' } );
		function syncQuick() {
			Array.prototype.forEach.call( quickWrap.children, function ( btn ) {
				btn.classList.toggle( 'is-active', parseInt( btn.dataset.val, 10 ) === state.persons );
			} );
		}
		[ 2, 10, 20, 30, 50, 80 ].forEach( function ( n ) {
			var btn = el( 'button', { type: 'button', 'data-val': n, onclick: function () {
				setPersons( n );
			} }, [ n === 80 ? '80+' : String( n ) ] );
			quickWrap.appendChild( btn );
		} );
		syncQuick();
		frag.appendChild( quickWrap );
		frag.appendChild( navEl );
		return frag;
	}

	function stepEventType() {
		var frag = el( 'div', {}, [
			el( 'h3', { class: 'p20-rf__step-title' }, [ 'Was haben Sie vor?' ] ),
			el( 'p', { class: 'p20-rf__step-sub' }, [ 'Wählen Sie die Art Ihrer Veranstaltung.' ] ),
		] );
		var grid = el( 'div', { class: 'p20-rf__grid' } );
		var events = ( state.remote && state.remote.events ) || [];
		var shown = events.slice( 0, 8 );
		if ( ! shown.length ) {
			shown = [ { slug: 'sonstiges', name: 'Sonstiges' } ];
		}
		shown.forEach( function ( ev ) {
			var card = el( 'button', {
				type: 'button',
				class: 'p20-rf__card' + ( state.event_type === ev.slug ? ' is-selected' : '' ),
				onclick: function () {
					state.event_type = ev.slug;
					state.event_type_label = ev.name;
					render();
				},
			}, [
				el( 'span', { class: 'p20-rf__card-check' }, [ '✓' ] ),
				el( 'span', { class: 'p20-rf__card-icon', html: eventIcon( ev.slug ) } ),
				el( 'span', { class: 'p20-rf__card-label' }, [ ev.name ] ),
			] );
			grid.appendChild( card );
		} );
		frag.appendChild( grid );
		frag.appendChild( nav( true, goNext, state.event_type ? 'Weiter →' : 'Überspringen →' ) );
		return frag;
	}

	function stepDuration() {
		var frag = el( 'div', {}, [
			el( 'h3', { class: 'p20-rf__step-title' }, [ 'Wie lange benötigen Sie den Raum?' ] ),
		] );
		var grid = el( 'div', { class: 'p20-rf__grid' } );
		var durations = ( state.remote && state.remote.durations ) || { '2h': '2 Stunden', '4h': '4 Stunden', ganztags: 'Ganztags', unsicher: 'Noch nicht sicher' };
		Object.keys( durations ).forEach( function ( key ) {
			var card = el( 'button', {
				type: 'button',
				class: 'p20-rf__card' + ( state.duration === key ? ' is-selected' : '' ),
				onclick: function () {
					state.duration = key;
					state.duration_label = durations[ key ];
					render();
				},
			}, [
				el( 'span', { class: 'p20-rf__card-check' }, [ '✓' ] ),
				el( 'span', { class: 'p20-rf__card-label' }, [ durations[ key ] ] ),
			] );
			grid.appendChild( card );
		} );
		frag.appendChild( grid );
		frag.appendChild( nav( true, goNext, state.duration ? 'Weiter →' : 'Überspringen →' ) );
		return frag;
	}

	function stepSeating() {
		var frag = el( 'div', {}, [
			el( 'h3', { class: 'p20-rf__step-title' }, [ 'Wie möchten Sie den Raum nutzen?' ] ),
			el( 'p', { class: 'p20-rf__step-sub' }, [ 'Gewünschte Bestuhlung.' ] ),
		] );
		var grid = el( 'div', { class: 'p20-rf__grid' } );
		var seating = ( state.remote && state.remote.seating_types ) || {};
		var options = Object.assign( {}, seating, { unsicher: 'Noch nicht sicher' } );
		Object.keys( options ).forEach( function ( key ) {
			var card = el( 'button', {
				type: 'button',
				class: 'p20-rf__card' + ( state.seating === key ? ' is-selected' : '' ),
				onclick: function () {
					state.seating = key;
					state.seating_label = options[ key ];
					render();
				},
			}, [
				el( 'span', { class: 'p20-rf__card-check' }, [ '✓' ] ),
				'unsicher' === key ? el( 'span', {} ) : seatingPictogram( key ),
				el( 'span', { class: 'p20-rf__card-label' }, [ options[ key ] ] ),
			] );
			grid.appendChild( card );
		} );
		frag.appendChild( grid );
		frag.appendChild( nav( true, goNext, state.seating ? 'Weiter →' : 'Überspringen →' ) );
		return frag;
	}

	function stepFeatures() {
		var frag = el( 'div', {}, [
			el( 'h3', { class: 'p20-rf__step-title' }, [ 'Welche Ausstattung benötigen Sie?' ] ),
			el( 'p', { class: 'p20-rf__step-sub' }, [ 'Mehrfachauswahl möglich.' ] ),
		] );
		var grid = el( 'div', { class: 'p20-rf__grid' } );
		var features = ( state.remote && state.remote.features ) || [];
		features.forEach( function ( f ) {
			var selected = state.features.indexOf( f.id ) !== -1;
			var toggle = el( 'div', {
				class: 'p20-rf__toggle' + ( selected ? ' is-selected' : '' ),
				onclick: function () {
					var idx = state.features.indexOf( f.id );
					if ( idx === -1 ) {
						state.features.push( f.id );
					} else {
						state.features.splice( idx, 1 );
					}
					render();
				},
			}, [
				el( 'span', { class: 'p20-rf__toggle-box' }, [ selected ? '✓' : '' ] ),
				el( 'span', { class: 'p20-rf__toggle-label' }, [ f.name ] ),
			] );
			grid.appendChild( toggle );
		} );
		frag.appendChild( grid );
		frag.appendChild( nav( true, goNext, state.features.length ? 'Weiter →' : 'Überspringen →' ) );
		return frag;
	}

	function stepCatering() {
		var frag = el( 'div', {}, [
			el( 'h3', { class: 'p20-rf__step-title' }, [ 'Darf es noch etwas dazu sein?' ] ),
			el( 'p', { class: 'p20-rf__step-sub' }, [ 'Verpflegung kann individuell auf Ihre Veranstaltung abgestimmt werden.' ] ),
		] );
		var grid = el( 'div', { class: 'p20-rf__grid' } );
		var catering = ( state.remote && state.remote.catering ) || [];
		catering.forEach( function ( c ) {
			var selected = state.catering.indexOf( c.id ) !== -1;
			var toggle = el( 'div', {
				class: 'p20-rf__toggle' + ( selected ? ' is-selected' : '' ),
				onclick: function () {
					var idx = state.catering.indexOf( c.id );
					if ( idx === -1 ) {
						state.catering.push( c.id );
					} else {
						state.catering.splice( idx, 1 );
					}
					render();
				},
			}, [
				el( 'span', { class: 'p20-rf__toggle-box' }, [ selected ? '✓' : '' ] ),
				el( 'span', { class: 'p20-rf__toggle-label' }, [ c.name ] ),
			] );
			grid.appendChild( toggle );
		} );
		frag.appendChild( grid );
		frag.appendChild( nav( true, goNext, 'Ergebnisse ansehen →' ) );
		return frag;
	}

	function runMatch() {
		state.resultsPage = 0;
		var body = {
			persons: state.persons,
			event_type: state.event_type,
			duration: state.duration,
			seating: state.seating,
			features: state.features,
			catering: state.catering,
		};
		state.view = 'results';
		state.matchResult = null;
		render();
		withMinDelay( apiPost( '/match', body ), MIN_LOADER_MS_SHORT ).then( function ( res ) {
			if ( ! res.ok || ! res.data ) {
				state.view = 'error';
				state.loadError = 'Die Suche konnte nicht durchgeführt werden. Bitte versuchen Sie es erneut.';
				render();
				return;
			}
			state.matchResult = res.data;
			render();
		} ).catch( function () {
			state.view = 'error';
			state.loadError = 'Die Suche konnte nicht durchgeführt werden. Bitte versuchen Sie es erneut.';
			render();
		} );
	}

	function featureNames( room ) {
		return room.features.map( function ( f ) {
			return f.name + ( 'optional' === f.state ? ' (zubuchbar)' : '' );
		} );
	}

	var RESULTS_PER_PAGE = 3;

	function paginatedGrid( rooms, pageStateKey ) {
		var container = el( 'div', {} );
		var totalPages = Math.max( 1, Math.ceil( rooms.length / RESULTS_PER_PAGE ) );
		if ( state[ pageStateKey ] >= totalPages ) {
			state[ pageStateKey ] = totalPages - 1;
		}

		function draw() {
			container.innerHTML = '';
			var page = state[ pageStateKey ];
			var start = page * RESULTS_PER_PAGE;
			var pageRooms = rooms.slice( start, start + RESULTS_PER_PAGE );

			var grid = el( 'div', { class: 'p20-rf__results-grid' } );
			pageRooms.forEach( function ( room ) {
				grid.appendChild( roomCard( room ) );
			} );
			container.appendChild( grid );

			if ( totalPages > 1 ) {
				var prevBtn = el( 'button', {
					class: 'p20-rf__pager-btn',
					type: 'button',
					'aria-label': 'Vorherige Räume',
					disabled: 0 === page ? 'disabled' : null,
					onclick: function () {
						state[ pageStateKey ] = Math.max( 0, state[ pageStateKey ] - 1 );
						draw();
					},
				}, [ '←' ] );
				var nextBtn = el( 'button', {
					class: 'p20-rf__pager-btn',
					type: 'button',
					'aria-label': 'Weitere Räume',
					disabled: page >= totalPages - 1 ? 'disabled' : null,
					onclick: function () {
						state[ pageStateKey ] = Math.min( totalPages - 1, state[ pageStateKey ] + 1 );
						draw();
					},
				}, [ '→' ] );
				var label = el( 'span', { class: 'p20-rf__pager-label' }, [ ( page + 1 ) + ' / ' + totalPages ] );
				container.appendChild( el( 'div', { class: 'p20-rf__results-pager' }, [ prevBtn, label, nextBtn ] ) );
			}
		}

		draw();
		return container;
	}

	function statBlock( label, value ) {
		return el( 'div', { class: 'p20-rf__stat' }, [
			el( 'span', { class: 'p20-rf__stat-label' }, [ label ] ),
			el( 'span', { class: 'p20-rf__stat-value' }, [ value || '—' ] ),
		] );
	}

	function roomCard( room, altBadge ) {
		var img = room.image
			? el( 'img', { src: room.image, alt: room.name, loading: 'lazy' } )
			: el( 'div', { style: 'width:100%;height:100%;background:#e6dcc9' } );

		var badge = el( 'span', { class: 'p20-rf__room-badge' + ( altBadge ? ' p20-rf__room-badge--alt' : '' ) }, [ altBadge || room.label ] );

		// Preis, Raumgröße und Kapazität sind die wichtigsten Entscheidungskriterien
		// und werden deshalb als eigene, ausgeschriebene Werte hervorgehoben.
		// Die Kapazität ist die allgemeine Raumkapazität, unabhängig von der
		// gewählten Bestuhlung (die je nach Bestuhlungsart variiert, siehe Detailansicht).
		var stats = el( 'div', { class: 'p20-rf__room-stats' }, [
			statBlock( 'Größe', room.size_sqm ? room.size_sqm + ' m²' : '' ),
			statBlock( 'Kapazität', room.capacity_max ? 'bis ' + room.capacity_max + ' Personen' : '' ),
			statBlock( 'Preis', room.price_display ),
		] );

		var tags = el( 'ul', { class: 'p20-rf__room-tags' }, featureNames( room ).slice( 0, 3 ).map( function ( f ) {
			return el( 'li', {}, [ f ] );
		} ) );

		return el( 'div', { class: 'p20-rf__room-card' }, [
			el( 'div', { class: 'p20-rf__room-media' }, [ img, badge ] ),
			el( 'div', { class: 'p20-rf__room-body' }, [
				el( 'h3', { class: 'p20-rf__room-name' }, [ room.name ] ),
				stats,
				el( 'p', { class: 'p20-rf__room-desc' }, [ room.short_desc || '' ] ),
				tags,
				el( 'div', { class: 'p20-rf__room-actions' }, [
					el( 'button', { class: 'p20-rf-btn p20-rf-btn--outline', onclick: function () { openModal( room ); } }, [ 'Raum ansehen' ] ),
					el( 'button', { class: 'p20-rf-btn p20-rf-btn--primary', onclick: function () { openRequest( room ); } }, [ 'Unverbindlich anfragen' ] ),
				] ),
			] ),
		] );
	}

	function renderResults() {
		var wrap = el( 'div', {} );

		if ( ! state.matchResult ) {
			wrap.appendChild( renderLogoLoader() );
			return wrap;
		}

		var res = state.matchResult;

		if ( res.matches && res.matches.length ) {
			wrap.appendChild( el( 'div', { class: 'p20-rf__results-head' }, [
				el( 'span', { class: 'p20-rf__kicker' }, [ 'Ergebnis' ] ),
				el( 'h2', { class: 'p20-rf__headline' }, [ 'Diese Räume passen zu Ihren Plänen.' ] ),
			] ) );
			wrap.appendChild( paginatedGrid( res.matches, 'resultsPage' ) );
		} else {
			wrap.appendChild( el( 'div', { class: 'p20-rf__empty' }, [
				el( 'span', { class: 'p20-rf__kicker' }, [ 'Kein exakter Treffer' ] ),
				el( 'h2', { class: 'p20-rf__headline' }, [ 'Ihre Veranstaltung braucht etwas mehr Raum.' ] ),
				el( 'p', { class: 'p20-rf__subline', style: 'margin-left:auto;margin-right:auto' }, [ 'Wir haben passende Alternativen für Sie zusammengestellt – oder sprechen gemeinsam eine individuelle Lösung ab.' ] ),
				el( 'button', { class: 'p20-rf-btn p20-rf-btn--primary', onclick: function () { openRequest( null ); } }, [ 'Individuelle Lösung anfragen' ] ),
			] ) );
			if ( res.fallback && res.fallback.length ) {
				var altGrid = el( 'div', { class: 'p20-rf__results-grid' } );
				res.fallback.forEach( function ( room ) {
					altGrid.appendChild( roomCard( room, 'Alternative' ) );
				} );
				wrap.appendChild( altGrid );
			}
		}

		wrap.appendChild( el( 'div', { class: 'p20-rf__nav' }, [
			el( 'button', { class: 'p20-rf-btn p20-rf-btn--ghost', onclick: function () { state.view = 'wizard'; state.step = 0; render(); } }, [ '← Neue Suche' ] ),
		] ) );

		return wrap;
	}

	function openModal( room ) {
		state.modalRoom = room;
		apiGet( '/room/' + room.id ).then( function ( full ) {
			state.modalRoom = full;
			render();
		} );
		render();
	}

	function closeModal() {
		if ( galleryCleanup ) {
			galleryCleanup();
			galleryCleanup = null;
		}
		state.modalRoom = null;
		render();
	}

	var GALLERY_AUTOPLAY_MS = 5000;
	var galleryCleanup = null;

	function gallerySlideshow( images, altText ) {
		if ( galleryCleanup ) {
			galleryCleanup();
			galleryCleanup = null;
		}

		var container = el( 'div', { class: 'p20-rf__modal-gallery' } );
		var index = 0;
		var timer = null;

		function stopAutoplay() {
			if ( timer ) {
				clearInterval( timer );
				timer = null;
			}
		}

		function startAutoplay() {
			stopAutoplay();
			if ( images.length > 1 ) {
				timer = setInterval( function () {
					goTo( ( index + 1 ) % images.length );
				}, GALLERY_AUTOPLAY_MS );
			}
		}

		function goTo( i ) {
			index = i;
			draw();
		}

		function draw() {
			container.innerHTML = '';
			container.appendChild( el( 'img', { src: images[ index ], alt: altText } ) );

			if ( images.length > 1 ) {
				container.appendChild( el( 'button', {
					type: 'button',
					class: 'p20-rf__gallery-nav p20-rf__gallery-nav--prev',
					'aria-label': 'Vorheriges Bild',
					onclick: function () { stopAutoplay(); goTo( ( index - 1 + images.length ) % images.length ); startAutoplay(); },
				}, [ '‹' ] ) );
				container.appendChild( el( 'button', {
					type: 'button',
					class: 'p20-rf__gallery-nav p20-rf__gallery-nav--next',
					'aria-label': 'Nächstes Bild',
					onclick: function () { stopAutoplay(); goTo( ( index + 1 ) % images.length ); startAutoplay(); },
				}, [ '›' ] ) );

				var dots = el( 'div', { class: 'p20-rf__gallery-dots' } );
				images.forEach( function ( _, i ) {
					dots.appendChild( el( 'button', {
						type: 'button',
						class: 'p20-rf__gallery-dot' + ( i === index ? ' is-active' : '' ),
						'aria-label': 'Bild ' + ( i + 1 ) + ' von ' + images.length,
						onclick: function () { stopAutoplay(); goTo( i ); startAutoplay(); },
					} ) );
				} );
				container.appendChild( dots );
				container.appendChild( el( 'span', { class: 'p20-rf__gallery-count' }, [ ( index + 1 ) + ' / ' + images.length ] ) );
			}
		}

		draw();
		startAutoplay();
		container.addEventListener( 'mouseenter', stopAutoplay );
		container.addEventListener( 'mouseleave', startAutoplay );
		galleryCleanup = stopAutoplay;

		return container;
	}

	function renderModal() {
		var room = state.modalRoom;
		var gallery = ( room.gallery && room.gallery.length ) ? room.gallery : ( room.image ? [ room.image ] : [] );

		var seatingList = room.seating ? Object.keys( room.seating ).map( function ( key ) {
			var s = room.seating[ key ];
			return el( 'li', {}, [ s.label + ' – bis ' + s.capacity + ' Personen' ] );
		} ) : [];

		var featureList = ( room.features || [] ).map( function ( f ) {
			return el( 'li', {}, [ f.name + ( 'optional' === f.state ? ' (optional zubuchbar)' : '' ) ] );
		} );

		var prices = room.prices || {};
		var priceRows = [];
		if ( prices.on_request ) {
			priceRows.push( el( 'tr', {}, [ el( 'td', {}, [ 'Preis' ] ), el( 'td', {}, [ 'Auf Anfrage' ] ) ] ) );
		} else {
			if ( prices[ '2h' ] ) { priceRows.push( el( 'tr', {}, [ el( 'td', {}, [ '2 Stunden' ] ), el( 'td', {}, [ 'ab ' + prices[ '2h' ] + ' €' ] ) ] ) ); }
			if ( prices[ '4h' ] ) { priceRows.push( el( 'tr', {}, [ el( 'td', {}, [ '4 Stunden' ] ), el( 'td', {}, [ 'ab ' + prices[ '4h' ] + ' €' ] ) ] ) ); }
			if ( prices.ganztags ) { priceRows.push( el( 'tr', {}, [ el( 'td', {}, [ 'Ganztags' ] ), el( 'td', {}, [ 'ab ' + prices.ganztags + ' €' ] ) ] ) ); }
			if ( prices.individual ) { priceRows.push( el( 'tr', {}, [ el( 'td', {}, [ 'Individuell' ] ), el( 'td', {}, [ prices.individual ] ) ] ) ); }
		}

		var overlay = el( 'div', { class: 'p20-rf__overlay', onclick: closeModal } );
		var modal = el( 'div', { class: 'p20-rf__modal' }, [
			el( 'button', { class: 'p20-rf__modal-close', 'aria-label': 'Schließen', onclick: closeModal }, [ '✕' ] ),
			gallery.length ? gallerySlideshow( gallery, room.name ) : null,
			el( 'div', { class: 'p20-rf__modal-body' }, [
				el( 'h2', { class: 'p20-rf__room-name', style: 'font-size:26px' }, [ room.name ] ),
				el( 'p', { class: 'p20-rf__room-meta' }, [ [ room.size_sqm ? room.size_sqm + ' m²' : '', room.capacity_max ? 'bis ' + room.capacity_max + ' Personen' : '' ].filter( Boolean ).join( ' · ' ) ] ),
				room.description ? el( 'div', { class: 'p20-rf__modal-section', html: room.description } ) : el( 'p', {}, [ room.short_desc || '' ] ),
				seatingList.length ? el( 'div', { class: 'p20-rf__modal-section' }, [ el( 'h3', {}, [ 'Bestuhlungsvarianten' ] ), el( 'ul', { class: 'p20-rf__room-tags' }, seatingList ) ] ) : null,
				featureList.length ? el( 'div', { class: 'p20-rf__modal-section' }, [ el( 'h3', {}, [ 'Ausstattung' ] ), el( 'ul', { class: 'p20-rf__room-tags' }, featureList ) ] ) : null,
				( room.catering && room.catering.length ) ? el( 'div', { class: 'p20-rf__modal-section' }, [ el( 'h3', {}, [ 'Zusatzleistungen' ] ), el( 'ul', { class: 'p20-rf__room-tags' }, room.catering.map( function ( c ) { return el( 'li', {}, [ c ] ); } ) ) ] ) : null,
				priceRows.length ? el( 'div', { class: 'p20-rf__modal-section' }, [ el( 'h3', {}, [ 'Preise' ] ), el( 'table', { class: 'p20-rf__price-table' }, priceRows ) ] ) : null,
				el( 'button', { class: 'p20-rf-btn p20-rf-btn--primary p20-rf-btn--block', onclick: function () { closeModal(); openRequest( room ); } }, [ 'Diesen Raum anfragen' ] ),
			] ),
		] );

		return el( 'div', {}, [ overlay, modal ] );
	}

	function openRequest( room ) {
		state.requestRoom = room;
		state.submitError = '';
		state.view = 'request';
		renderRequestView();
	}

	function renderRequestView() {
		root.innerHTML = '';
		var wrap = el( 'div', { class: 'p20-rf__panel' } );

		if ( 'success' === state.view ) {
			wrap.appendChild( el( 'div', { class: 'p20-rf__success' }, [
				el( 'div', { class: 'p20-rf__success-icon' }, [ '✓' ] ),
				el( 'h2', { class: 'p20-rf__headline' }, [ 'Vielen Dank für Ihre Anfrage.' ] ),
				el( 'p', { class: 'p20-rf__subline', style: 'margin-left:auto;margin-right:auto' }, [ 'Wir prüfen Ihre Angaben und melden uns mit einem passenden Angebot. Dies ist noch keine verbindliche Buchung.' ] ),
				el( 'button', { class: 'p20-rf-btn p20-rf-btn--outline', onclick: function () {
					state.view = 'start';
					state.step = 0;
					state.matchResult = null;
					render();
				} }, [ 'Zurück zum Raumfinder' ] ),
			] ) );
			root.appendChild( wrap );
			return;
		}

		var room = state.requestRoom;

		wrap.appendChild( el( 'span', { class: 'p20-rf__kicker' }, [ 'Anfrage' ] ) );
		wrap.appendChild( el( 'h2', { class: 'p20-rf__step-title' }, [ room ? 'Anfrage für „' + room.name + '“' : 'Individuelle Anfrage' ] ) );

		var chips = [];
		if ( state.persons ) { chips.push( state.persons + ' Personen' ); }
		if ( state.event_type_label ) { chips.push( state.event_type_label ); }
		if ( state.duration_label ) { chips.push( state.duration_label ); }
		if ( state.seating_label ) { chips.push( state.seating_label ); }
		wrap.appendChild( el( 'div', { class: 'p20-rf__summary-chips' }, chips.map( function ( c ) { return el( 'span', {}, [ c ] ); } ) ) );

		// Restores whatever the visitor already typed if the form has to be
		// re-rendered (e.g. after a validation error) so nothing gets lost.
		var fields = {};
		function field( id, label, type, required, full ) {
			var input = 'textarea' === type
				? el( 'textarea', { id: id, name: id, rows: '4' } )
				: el( 'input', { id: id, name: id, type: type } );
			if ( state.requestDraft[ id ] ) {
				input.value = state.requestDraft[ id ];
			}
			input.addEventListener( 'input', function () {
				state.requestDraft[ id ] = input.value;
			} );
			fields[ id ] = input;
			return el( 'div', { class: 'p20-rf__field' + ( full ? ' p20-rf__field--full' : '' ) }, [
				el( 'label', { for: id }, [ label + ( required ? ' *' : '' ) ] ),
				input,
			] );
		}

		var formGrid = el( 'div', { class: 'p20-rf__form-grid' }, [
			field( 'first_name', 'Vorname', 'text', true ),
			field( 'last_name', 'Nachname', 'text', true ),
			field( 'company', 'Unternehmen', 'text', false ),
			field( 'email', 'E-Mail', 'email', true ),
			field( 'phone', 'Telefon', 'tel', false ),
			field( 'date', 'Gewünschtes Datum', 'date', false ),
			field( 'time', 'Uhrzeit', 'time', false ),
			field( 'message', 'Nachricht / besondere Wünsche', 'textarea', false, true ),
		] );

		var honeypot = el( 'input', { type: 'text', name: 'website', class: 'p20-rf__field-hp', tabindex: '-1', autocomplete: 'off' } );

		var settings = ( state.remote && state.remote.settings ) || {};
		var consentInput = el( 'input', { type: 'checkbox', id: 'consent' } );
		if ( state.requestDraft.consent ) {
			consentInput.checked = true;
		}
		consentInput.addEventListener( 'change', function () {
			state.requestDraft.consent = consentInput.checked;
		} );
		var privacyLabel = [ settings.privacy_text || 'Ich habe die Datenschutzerklärung gelesen und bin einverstanden.' ];
		var consentLabel = el( 'label', { for: 'consent' }, privacyLabel );
		var consent = el( 'div', { class: 'p20-rf__field-consent' }, [ consentInput, consentLabel ] );

		var errorBox = el( 'div', { class: 'p20-rf__error' }, [ state.submitError ] );

		var submitBtn = el( 'button', { class: 'p20-rf-btn p20-rf-btn--primary p20-rf-btn--block' }, [ state.submitting ? 'Wird gesendet …' : 'Unverbindlich anfragen' ] );
		if ( state.submitting ) {
			submitBtn.setAttribute( 'disabled', 'disabled' );
		}

		var form = el( 'form', {
			onsubmit: function ( e ) {
				e.preventDefault();
				submitRequest( fields, consentInput, honeypot, room );
			},
		}, [ formGrid, honeypot, consent, errorBox, el( 'div', { style: 'margin-top:20px' }, [ submitBtn ] ) ] );

		wrap.appendChild( form );

		wrap.appendChild( el( 'div', { class: 'p20-rf__nav' }, [
			el( 'button', { class: 'p20-rf-btn p20-rf-btn--ghost', type: 'button', onclick: function () {
				state.view = room ? 'results' : 'results';
				render();
			} }, [ '← Zurück' ] ),
		] ) );

		root.appendChild( wrap );
	}

	function submitRequest( fields, consentInput, honeypot, room ) {
		var body = {
			nonce: cfg.requestNonce,
			website: honeypot.value,
			first_name: fields.first_name.value.trim(),
			last_name: fields.last_name.value.trim(),
			company: fields.company.value.trim(),
			email: fields.email.value.trim(),
			phone: fields.phone.value.trim(),
			date: fields.date.value,
			time: fields.time.value,
			message: fields.message.value.trim(),
			consent: consentInput.checked,
			room_id: room ? room.id : 0,
			persons: state.persons,
			event_type_label: state.event_type_label,
			duration_label: state.duration_label,
			seating_label: state.seating_label,
			features_labels: [],
			catering_labels: [],
		};

		if ( ! body.first_name || ! body.last_name || ! body.email || ! body.consent ) {
			state.submitError = 'Bitte füllen Sie alle Pflichtfelder aus und bestätigen Sie die Datenschutzhinweise.';
			renderRequestView();
			return;
		}

		state.submitting = true;
		state.submitError = '';
		renderRequestView();

		apiPost( '/request', body ).then( function ( res ) {
			state.submitting = false;
			if ( res.ok && res.data && res.data.success ) {
				state.view = 'success';
				state.requestDraft = {};
				renderRequestView();
			} else {
				state.submitError = ( res.data && res.data.message ) || 'Ihre Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es erneut.';
				renderRequestView();
			}
		} ).catch( function () {
			state.submitting = false;
			state.submitError = 'Es gab ein Verbindungsproblem. Bitte versuchen Sie es erneut.';
			renderRequestView();
		} );
	}

	function init() {
		render();
		withMinDelay( apiGet( '/config' ), MIN_LOADER_MS ).then( function ( data ) {
			state.remote = data;
			state.view = 'start';
			render();
		} ).catch( function ( err ) {
			state.view = 'error';
			state.loadError = err && 403 === err.status
				? 'Die Verbindung zur Raumfinder-Schnittstelle wurde blockiert (403). Bitte prüfen Sie Sicherheits- oder Cache-Plugins.'
				: 'Der Raumfinder konnte nicht geladen werden. Bitte laden Sie die Seite neu.';
			render();
		} );
	}

	init();
} )();
