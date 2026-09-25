/**
 * Ask before a minimum refuses the customer.
 *
 * Any shop can build its own "add to cart" control, and many do: WooCommerce's
 * form, a page builder's link, a theme's hand-written button. Rather than know
 * them all, this listens for clicks on the document in the CAPTURE phase, which
 * runs before any handler bound on the element or delegated through jQuery.
 * If the click looks like an "add to cart" for a product that has a minimum the
 * customer has not met, the event is stopped there, the dialog asks whether to
 * make up the difference, and only on "yes" is the original click replayed —
 * after every quantity field for that product has been set, so whichever field
 * the shop's own script reads, it reads the agreed number.
 *
 * Nothing here enforces anything: the server refuses regardless (see
 * CRFW_Cart). This exists so the customer is asked instead of refused.
 *
 * @package CartRulesForWooCommerce
 */
( function () {
	'use strict';

	var cfg = window.crfwAddToCart || {};
	if ( ! cfg.endpoint ) {
		return;
	}

	var cache = {};       // product id -> rules, filled on first use.
	var replaying = null; // The element whose click we are replaying.

	/* ---------------------------------------------------------------- utils */

	function toLatinDigits( value ) {
		return String( value == null ? '' : value )
			.replace( /[۰-۹]/g, function ( d ) { return d.charCodeAt( 0 ) - 0x06F0; } )
			.replace( /[٠-٩]/g, function ( d ) { return d.charCodeAt( 0 ) - 0x0660; } );
	}

	function toInt( value ) {
		var n = parseInt( toLatinDigits( value ), 10 );
		return isNaN( n ) ? 0 : n;
	}

	function queryParam( url, name ) {
		var m = String( url || '' ).match( new RegExp( '[?&]' + name + '=([^&#]*)' ) );
		return m ? decodeURIComponent( m[ 1 ] ) : '';
	}

	/* ------------------------------------------------- reading the intention */

	/**
	 * A quantity widget: its input, its + and - steppers, its wrapper.
	 *
	 * These are the customer's to use freely. They often sit inside an element
	 * that names the product, which is exactly what makes them easy to mistake
	 * for the button — so they are ruled out before anything else is considered.
	 */
	function isQuantityControl( node ) {
		if ( ! node || ! node.closest ) {
			return false;
		}
		if ( node.matches && node.matches( 'input, select, textarea, label' ) ) {
			return true;
		}
		return !! node.closest( '.quantity, .qty, .nima-qty, [class*="qty-"], [class*="-qty"], [class*="quantity"]' );
	}

	/**
	 * The element that carries an "add to cart" intention, if this click has one.
	 *
	 * Only a real control counts — a button, a submit, or a link that says it adds
	 * to the cart. A container that merely carries the product's id is not one.
	 */
	function controlFor( target ) {
		if ( isQuantityControl( target ) ) {
			return null;
		}

		var known = target.closest(
			[
				'.add_to_cart_button',
				'.ajax_add_to_cart',
				'.single_add_to_cart_button',
				'button[name="add-to-cart"]',
				'input[name="add-to-cart"]',
				'a[href*="add-to-cart="]'
			].join( ',' )
		);
		if ( known ) {
			return isQuantityControl( known ) ? null : known;
		}

		// A theme's own control: a button or submit that names the product.
		var byData = target.closest( 'button[data-product_id], button[data-product-id], input[data-product_id], input[data-product-id]' );
		if ( byData && ! isQuantityControl( byData ) ) {
			return byData;
		}

		// A link that names the product counts only when it says it adds to the cart —
		// otherwise it is just a link to the product.
		var link = target.closest( 'a[data-product_id], a[data-product-id]' );
		if ( link && ! isQuantityControl( link ) && /add[-_ ]?to[-_ ]?cart|\batc\b|cart/i.test( link.className + ' ' + ( link.getAttribute( 'data-action' ) || '' ) ) ) {
			return link;
		}

		// WooCommerce's own form: its submit button.
		var form = target.closest( 'form.cart' );
		if ( form && target.closest( '[type="submit"], button' ) && ! isQuantityControl( target ) ) {
			return target.closest( '[type="submit"], button' );
		}
		return null;
	}

	/**
	 * Which product the control is for. Variations answer for themselves, and the
	 * server maps them back to the product the rules live on.
	 */
	function productIdFor( el ) {
		var form = el.closest ? el.closest( 'form.cart, form' ) : null;
		var candidates = [
			el.getAttribute( 'data-variation_id' ),
			el.getAttribute( 'data-variation-id' ),
			form && form.querySelector( 'input[name="variation_id"]' ) ? form.querySelector( 'input[name="variation_id"]' ).value : '',
			el.getAttribute( 'data-product_id' ),
			el.getAttribute( 'data-product-id' ),
			el.value && 'add-to-cart' === el.getAttribute( 'name' ) ? el.value : '',
			queryParam( el.getAttribute( 'href' ), 'add-to-cart' ),
			form && form.querySelector( 'input[name="add-to-cart"]' ) ? form.querySelector( 'input[name="add-to-cart"]' ).value : '',
			form && form.querySelector( 'button[name="add-to-cart"]' ) ? form.querySelector( 'button[name="add-to-cart"]' ).value : ''
		];
		for ( var i = 0; i < candidates.length; i++ ) {
			var id = toInt( candidates[ i ] );
			if ( id > 0 ) {
				return id;
			}
		}
		return 0;
	}

	/**
	 * Every quantity field that belongs to this product, so the agreed number is
	 * the one the shop's own script will read — whichever field that happens to be.
	 */
	function quantityFields( el, productId ) {
		var fields = [];
		var seen = [];
		var selector = 'input[name="quantity"], input.qty, .qty input, input[data-qty], .nima-qty-input, input[type="number"]';

		function add( node ) {
			if ( node && -1 === seen.indexOf( node ) ) {
				seen.push( node );
				fields.push( node );
			}
		}

		// The field next to the control comes first: it is the one the customer typed in.
		var scope = el.closest ? el.closest( 'form.cart, .product, .nima-atc-wrap, .elementor-widget-container, li, article, div' ) : null;
		var hops = 0;
		while ( scope && hops < 4 ) {
			var local = scope.querySelectorAll( selector );
			if ( local.length ) {
				for ( var i = 0; i < local.length; i++ ) {
					add( local[ i ] );
				}
				break;
			}
			scope = scope.parentElement;
			hops++;
		}

		// Then any field elsewhere on the page that names the same product — a second
		// layout (a mobile copy, a sticky bar) usually carries one.
		var boxes = document.querySelectorAll( '[data-product_id="' + productId + '"], [data-product-id="' + productId + '"]' );
		for ( var b = 0; b < boxes.length; b++ ) {
			var inputs = boxes[ b ].querySelectorAll( selector );
			for ( var j = 0; j < inputs.length; j++ ) {
				add( inputs[ j ] );
			}
		}
		return fields;
	}

	function intendedQuantity( el, fields ) {
		var dataQty = toInt( el.getAttribute( 'data-quantity' ) );
		var visible = fields.filter( function ( f ) {
			return null !== f.offsetParent;
		} );
		var field = visible.length ? visible[ 0 ] : fields[ 0 ];
		if ( field ) {
			var typed = toInt( field.value );
			if ( typed > 0 ) {
				return typed;
			}
		}
		if ( dataQty > 0 ) {
			return dataQty;
		}
		var href = toInt( queryParam( el.getAttribute( 'href' ), 'quantity' ) );
		return href > 0 ? href : 1;
	}

	/* ------------------------------------------------------------- the rules */

	function fetchRules( ids ) {
		var missing = ids.filter( function ( id ) {
			return ! Object.prototype.hasOwnProperty.call( cache, id );
		} );
		if ( ! missing.length ) {
			return Promise.resolve();
		}
		// The answer describes this visitor's own cart, and a page cache in front of
		// the site will happily serve someone else's copy of a plain GET — so the
		// request carries a value that is never the same twice.
		var url = cfg.endpoint + ( cfg.endpoint.indexOf( '?' ) > -1 ? '&' : '?' ) +
			'ids=' + missing.join( ',' ) + '&_=' + Date.now();
		return fetch( url, { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				missing.forEach( function ( id ) {
					cache[ id ] = ( data && data.products && data.products[ id ] ) || { min_qty: 0, min_amount: 0 };
				} );
			} )
			.catch( function () {
				missing.forEach( function ( id ) { cache[ id ] = { min_qty: 0, min_amount: 0 }; } );
			} );
	}

	/**
	 * How many this click should add for the product to clear its own bar, and
	 * why. Returns null when the customer is already fine.
	 */
	function shortfall( rules, intended ) {
		if ( ! rules ) {
			return null;
		}
		var need = 0;
		var reason = null;

		if ( rules.min_qty > 0 && ( rules.in_cart_qty + intended ) < rules.min_qty ) {
			need = rules.min_qty - rules.in_cart_qty;
			reason = 'qty';
		}
		if ( rules.min_amount > 0 && rules.price > 0 ) {
			var afterAmount = rules.in_cart_amount + ( intended * rules.price );
			if ( afterAmount < rules.min_amount ) {
				var byAmount = Math.ceil( ( rules.min_amount - rules.in_cart_amount ) / rules.price );
				if ( byAmount > need ) {
					need = byAmount;
					reason = 'amount';
				}
			}
		}
		return need > intended ? { quantity: need, reason: reason } : null;
	}

	/* ----------------------------------------------------------- the dialog */

	function ask( rules, gap ) {
		var i18n = cfg.i18n || {};
		var quantity = String( gap.quantity ).replace( /\d/g, function ( d ) {
			return ( 0 ).toLocaleString().charAt( 0 ) === '0' ? d : d; // keep the site's own digits alone
		} );
		var amount = 'amount' === gap.reason
			? ( rules.min_amount_html || rules.min_amount )
			: ( i18n.items || '%s' ).replace( '%s', rules.min_qty );
		var template = 'amount' === gap.reason ? i18n.askAmount : i18n.askQty;
		var text = String( template || '' )
			.replace( '%1$s', rules.name || '' )
			.replace( '%2$s', amount )
			.replace( '%3$s', ( i18n.items || '%s' ).replace( '%s', quantity ) );

		return new Promise( function ( resolve ) {
			var overlay = document.createElement( 'div' );
			overlay.className = 'crfw-modal';
			overlay.setAttribute( 'role', 'dialog' );
			overlay.setAttribute( 'aria-modal', 'true' );
			overlay.innerHTML =
				'<div class="crfw-modal__box">' +
					'<h2 class="crfw-modal__title"></h2>' +
					'<p class="crfw-modal__text"></p>' +
					'<div class="crfw-modal__actions">' +
						'<button type="button" class="crfw-modal__btn crfw-modal__btn--ghost"></button>' +
						'<button type="button" class="crfw-modal__btn crfw-modal__btn--primary"></button>' +
					'</div>' +
				'</div>';
			overlay.querySelector( '.crfw-modal__title' ).textContent = i18n.title || '';
			overlay.querySelector( '.crfw-modal__text' ).textContent = text;
			var cancel = overlay.querySelector( '.crfw-modal__btn--ghost' );
			var confirm = overlay.querySelector( '.crfw-modal__btn--primary' );
			cancel.textContent = i18n.cancel || 'Cancel';
			confirm.textContent = i18n.confirm || 'OK';

			function close( answer ) {
				document.removeEventListener( 'keydown', onKey, true );
				if ( overlay.parentNode ) {
					overlay.parentNode.removeChild( overlay );
				}
				resolve( answer );
			}
			function onKey( e ) {
				if ( 'Escape' === e.key ) {
					close( false );
				}
			}

			cancel.addEventListener( 'click', function () { close( false ); } );
			confirm.addEventListener( 'click', function () { close( true ); } );
			overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === overlay ) {
					close( false );
				}
			} );
			document.addEventListener( 'keydown', onKey, true );
			document.body.appendChild( overlay );
			confirm.focus();
		} );
	}

	/* ------------------------------------------------------------ the action */

	/**
	 * Put the agreed quantity where the shop's own code will look for it, and
	 * hand back a function that puts everything back as it was. The controls are
	 * the page's, not ours: a raised quantity that stayed behind would silently
	 * change what the *next* click adds.
	 */
	function applyQuantity( el, fields, quantity ) {
		var undo = [];

		fields.forEach( function ( field ) {
			var previous = field.value;
			undo.push( function () {
				field.value = previous;
				field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
			field.value = quantity;
			field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		if ( el.hasAttribute( 'data-quantity' ) ) {
			var previousQty = el.getAttribute( 'data-quantity' );
			undo.push( function () { el.setAttribute( 'data-quantity', previousQty ); } );
			el.setAttribute( 'data-quantity', quantity );
		}

		var href = el.getAttribute( 'href' );
		if ( href && href.indexOf( 'add-to-cart=' ) > -1 ) {
			undo.push( function () { el.setAttribute( 'href', href ); } );
			el.setAttribute(
				'href',
				href.indexOf( 'quantity=' ) > -1
					? href.replace( /quantity=\d+/, 'quantity=' + quantity )
					: href + '&quantity=' + quantity
			);
		}

		return function () {
			undo.forEach( function ( fn ) { fn(); } );
		};
	}

	function replay( el ) {
		replaying = el;
		el.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true, view: window } ) );
		replaying = null;
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}
		var el = controlFor( target );
		if ( ! el || el === replaying ) {
			return;
		}
		var productId = productIdFor( el );
		if ( ! productId ) {
			return;
		}
		var cached = cache[ productId ];
		var fields = quantityFields( el, productId );
		var intended = intendedQuantity( el, fields );

		// Known and satisfied: let the shop's own handlers run untouched.
		if ( cached && ! shortfall( cached, intended ) ) {
			return;
		}

		// Not known yet, or a gap: hold the click until we have answered it.
		event.preventDefault();
		event.stopImmediatePropagation();

		fetchRules( [ productId ] ).then( function () {
			var rules = cache[ productId ];
			var gap = shortfall( rules, intended );
			if ( ! gap ) {
				replay( el );
				return;
			}
			ask( rules, gap ).then( function ( yes ) {
				if ( ! yes ) {
					return; // Nothing is added at all — the customer said no.
				}
				var restore = applyQuantity( el, fields, gap.quantity );
				if ( rules ) {
					rules.in_cart_qty += gap.quantity;
					rules.in_cart_amount += gap.quantity * ( rules.price || 0 );
				}
				replay( el );
				// The shop's handler reads the fields while it runs; give it that
				// moment, then leave the page as we found it.
				window.setTimeout( restore, 1500 );
			} );
		} );
	}, true );

	// A cart change anywhere invalidates what we know about it.
	document.addEventListener( 'added_to_cart', function () { cache = {}; } );
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'added_to_cart removed_from_cart wc_fragments_refreshed', function () { cache = {}; } );
	}
} )();
