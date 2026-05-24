/**
 * Cohost block editor scripts.
 *
 * Each block is a server-rendered (dynamic) block: PHP outputs the HTML, and
 * <ServerSideRender /> in the editor calls back to PHP via REST so the editor
 * preview matches the front-end exactly. No JSX, no webpack — uses the WP
 * globals that ship with WordPress 5.0+.
 */
( function ( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var ServerSideRender  = ( wp.serverSideRender && wp.serverSideRender.default ) || wp.serverSideRender;
	var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
	var BlockControls     = wp.blockEditor && wp.blockEditor.BlockControls;
	var HeadingLevelDropdown = wp.blockEditor && wp.blockEditor.HeadingLevelDropdown;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var TextControl       = wp.components.TextControl;
	var TextareaControl   = wp.components.TextareaControl;
	var ToggleControl     = wp.components.ToggleControl;
	var __                = wp.i18n.__;

	// Cohost lettermark — the canonical brand mark.
	// The C inherits currentColor so WP's editor styling applies; the orange
	// square is hard-coded #f97316 (brand accent, never recolored).
	var COHOST_ICON = el(
		'svg',
		{ width: 24, height: 24, viewBox: '0 0 39 39', xmlns: 'http://www.w3.org/2000/svg' },
		el( 'g', { transform: 'translate(0, -17.5)' },
			el( 'path', {
				d: 'M13.92 50.576C11.296 50.576 8.928 49.984 6.816 48.8C4.736 47.584 3.072 45.936 1.824 43.856C0.608 41.776 0 39.44 0 36.848C0 34.256 0.608 31.936 1.824 29.888C3.04 27.808 4.704 26.176 6.816 24.992C8.928 23.808 11.296 23.216 13.92 23.216C15.872 23.216 17.68 23.552 19.344 24.224C21.008 24.896 22.432 25.84 23.616 27.056C24.8 28.24 25.648 29.648 26.16 31.28L19.92 33.968C19.472 32.656 18.704 31.616 17.616 30.848C16.56 30.08 15.328 29.696 13.92 29.696C12.672 29.696 11.552 30 10.56 30.608C9.6 31.216 8.832 32.064 8.256 33.152C7.712 34.24 7.44 35.488 7.44 36.896C7.44 38.304 7.712 39.552 8.256 40.64C8.832 41.728 9.6 42.576 10.56 43.184C11.552 43.792 12.672 44.096 13.92 44.096C15.36 44.096 16.608 43.712 17.664 42.944C18.72 42.176 19.472 41.136 19.92 39.824L26.16 42.56C25.68 44.096 24.848 45.472 23.664 46.688C22.48 47.904 21.056 48.864 19.392 49.568C17.728 50.24 15.904 50.576 13.92 50.576Z',
				fill: 'currentColor'
			} ),
			el( 'rect', { x: 29, y: 40, width: 10, height: 10, fill: '#f97316' } )
		)
	);

	function eventIdControl( props ) {
		return el( TextControl, {
			label: __( 'Event ID (override)', 'cohost' ),
			help: __( 'Leave blank to use the event from the page URL (the standard setup on the Event profile page). Set this only when you want to pin a specific event — e.g. a featured event on the homepage.', 'cohost' ),
			value: props.attributes.eventId || '',
			onChange: function ( v ) { props.setAttributes( { eventId: v } ); }
		} );
	}

	function ssr( name, attrs ) {
		if ( ! ServerSideRender ) {
			return el( 'div', null, __( 'ServerSideRender component unavailable.', 'cohost' ) );
		}
		return el( ServerSideRender, { block: name, attributes: attrs } );
	}

	// ------------------------------------------------------------------------
	// cohost/event-name
	// ------------------------------------------------------------------------
	registerBlockType( 'cohost/event-name', {
		title: __( 'Event name', 'cohost' ),
		category: 'cohost',
		icon: COHOST_ICON,
		description: __( 'The event title. Pick a heading level (H1 by default).', 'cohost' ),
		edit: function ( props ) {
			var attrs = props.attributes;
			return el( Fragment, null,
				HeadingLevelDropdown && BlockControls && el( BlockControls, null,
					el( HeadingLevelDropdown, {
						value: attrs.level,
						onChange: function ( level ) { props.setAttributes( { level: level } ); }
					} )
				),
				InspectorControls && el( InspectorControls, null,
					el( PanelBody, { title: __( 'Heading', 'cohost' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Heading level', 'cohost' ),
							value: String( attrs.level || 1 ),
							options: [
								{ label: 'H1', value: '1' },
								{ label: 'H2', value: '2' },
								{ label: 'H3', value: '3' },
								{ label: 'H4', value: '4' },
								{ label: 'H5', value: '5' },
								{ label: 'H6', value: '6' }
							],
							onChange: function ( v ) { props.setAttributes( { level: parseInt( v, 10 ) } ); }
						} )
					),
					el( PanelBody, { title: __( 'Event source', 'cohost' ), initialOpen: false },
						eventIdControl( props )
					)
				),
				ssr( 'cohost/event-name', attrs )
			);
		},
		save: function () { return null; }
	} );

	// ------------------------------------------------------------------------
	// cohost/event-date
	// ------------------------------------------------------------------------
	registerBlockType( 'cohost/event-date', {
		title: __( 'Event date', 'cohost' ),
		category: 'cohost',
		icon: COHOST_ICON,
		description: __( 'Event start / end dates. Choose what to show and how to format it.', 'cohost' ),
		edit: function ( props ) {
			var attrs = props.attributes;
			return el( Fragment, null,
				InspectorControls && el( InspectorControls, null,
					el( PanelBody, { title: __( 'Display', 'cohost' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'What to show', 'cohost' ),
							value: attrs.display || 'compact',
							options: [
								{ label: __( 'Compact (start full + end time only when same day)', 'cohost' ), value: 'compact' },
								{ label: __( 'Both start and end (full)', 'cohost' ), value: 'both' },
								{ label: __( 'Start only', 'cohost' ), value: 'start' },
								{ label: __( 'End only', 'cohost' ), value: 'end' }
							],
							onChange: function ( v ) { props.setAttributes( { display: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Format', 'cohost' ),
							value: attrs.format || 'datetime',
							options: [
								{ label: __( 'Date and time', 'cohost' ), value: 'datetime' },
								{ label: __( 'Date only', 'cohost' ), value: 'date' },
								{ label: __( 'Time only', 'cohost' ), value: 'time' },
								{ label: __( 'Custom (PHP format string)', 'cohost' ), value: 'custom' }
							],
							onChange: function ( v ) { props.setAttributes( { format: v } ); }
						} ),
						attrs.format === 'custom' && el( TextControl, {
							label: __( 'Custom format', 'cohost' ),
							help: __( 'PHP date() format. Examples: "M j, Y g:ia" → "Jan 1, 2026 9:00pm", "n/j/Y g:ia" → "1/1/2026 9:00pm".', 'cohost' ),
							value: attrs.customFormat || 'M j, Y g:ia',
							onChange: function ( v ) { props.setAttributes( { customFormat: v } ); }
						} ),
						( attrs.display === 'both' || attrs.display === 'compact' || ! attrs.display ) && el( TextControl, {
							label: __( 'Separator', 'cohost' ),
							help: __( 'String between start and end. Default " – ".', 'cohost' ),
							value: attrs.separator || ' – ',
							onChange: function ( v ) { props.setAttributes( { separator: v } ); }
						} )
					),
					el( PanelBody, { title: __( 'Event source', 'cohost' ), initialOpen: false },
						eventIdControl( props )
					)
				),
				ssr( 'cohost/event-date', attrs )
			);
		},
		save: function () { return null; }
	} );

	// ------------------------------------------------------------------------
	// cohost/event-flyer
	// ------------------------------------------------------------------------
	registerBlockType( 'cohost/event-flyer', {
		title: __( 'Event flyer', 'cohost' ),
		category: 'cohost',
		icon: COHOST_ICON,
		description: __( 'The event cover image.', 'cohost' ),
		edit: function ( props ) {
			var attrs = props.attributes;
			return el( Fragment, null,
				InspectorControls && el( InspectorControls, null,
					el( PanelBody, { title: __( 'Image', 'cohost' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Size', 'cohost' ),
							value: attrs.size || 'large',
							options: [
								{ label: __( 'Thumbnail',  'cohost' ), value: 'thumbnail' },
								{ label: __( 'Small',      'cohost' ), value: 'small' },
								{ label: __( 'Medium',     'cohost' ), value: 'medium' },
								{ label: __( 'Large',      'cohost' ), value: 'large' },
								{ label: __( 'Full width', 'cohost' ), value: 'full' }
							],
							onChange: function ( v ) { props.setAttributes( { size: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Aspect ratio', 'cohost' ),
							value: attrs.aspect || 'auto',
							options: [
								{ label: __( 'Auto (original)', 'cohost' ), value: 'auto' },
								{ label: '16/9',  value: '16/9' },
								{ label: '4/3',   value: '4/3' },
								{ label: '1/1',   value: '1/1' },
								{ label: '3/4',   value: '3/4' }
							],
							onChange: function ( v ) { props.setAttributes( { aspect: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Alignment', 'cohost' ),
							value: attrs.align || 'center',
							options: [
								{ label: __( 'Left',   'cohost' ), value: 'left' },
								{ label: __( 'Center', 'cohost' ), value: 'center' },
								{ label: __( 'Right',  'cohost' ), value: 'right' }
							],
							onChange: function ( v ) { props.setAttributes( { align: v } ); }
						} )
					),
					el( PanelBody, { title: __( 'Event source', 'cohost' ), initialOpen: false },
						eventIdControl( props )
					)
				),
				ssr( 'cohost/event-flyer', attrs )
			);
		},
		save: function () { return null; }
	} );

	// ------------------------------------------------------------------------
	// cohost/event-venue
	// ------------------------------------------------------------------------
	registerBlockType( 'cohost/event-venue', {
		title: __( 'Event venue', 'cohost' ),
		category: 'cohost',
		icon: COHOST_ICON,
		edit: function ( props ) {
			var attrs = props.attributes;
			return el( Fragment, null,
				InspectorControls && el( InspectorControls, null,
					el( PanelBody, { title: __( 'Display', 'cohost' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Show', 'cohost' ),
							value: attrs.display || 'name+address',
							options: [
								{ label: __( 'Name and address', 'cohost' ), value: 'name+address' },
								{ label: __( 'Name only',        'cohost' ), value: 'name' },
								{ label: __( 'Address only',     'cohost' ), value: 'address' }
							],
							onChange: function ( v ) { props.setAttributes( { display: v } ); }
						} )
					),
					el( PanelBody, { title: __( 'Event source', 'cohost' ), initialOpen: false },
						eventIdControl( props )
					)
				),
				ssr( 'cohost/event-venue', attrs )
			);
		},
		save: function () { return null; }
	} );

	// ------------------------------------------------------------------------
	// cohost/event-summary
	// ------------------------------------------------------------------------
	registerBlockType( 'cohost/event-summary', {
		title: __( 'Event summary', 'cohost' ),
		category: 'cohost',
		icon: COHOST_ICON,
		edit: function ( props ) {
			return el( Fragment, null,
				InspectorControls && el( InspectorControls, null,
					el( PanelBody, { title: __( 'Event source', 'cohost' ), initialOpen: false },
						eventIdControl( props )
					)
				),
				ssr( 'cohost/event-summary', props.attributes )
			);
		},
		save: function () { return null; }
	} );

	// ------------------------------------------------------------------------
	// cohost/event-content
	// ------------------------------------------------------------------------
	registerBlockType( 'cohost/event-content', {
		title: __( 'Event content', 'cohost' ),
		category: 'cohost',
		icon: COHOST_ICON,
		description: __( 'The structured content blocks (description, gallery, FAQ, etc.) authored on the Cohost dashboard.', 'cohost' ),
		edit: function ( props ) {
			return el( Fragment, null,
				InspectorControls && el( InspectorControls, null,
					el( PanelBody, { title: __( 'Event source', 'cohost' ), initialOpen: false },
						eventIdControl( props )
					)
				),
				ssr( 'cohost/event-content', props.attributes )
			);
		},
		save: function () { return null; }
	} );

	// ------------------------------------------------------------------------
	// cohost/event-tickets
	// ------------------------------------------------------------------------
	registerBlockType( 'cohost/event-tickets', {
		title: __( 'Event tickets', 'cohost' ),
		category: 'cohost',
		icon: COHOST_ICON,
		edit: function ( props ) {
			var attrs = props.attributes;
			return el( Fragment, null,
				InspectorControls && el( InspectorControls, null,
					el( PanelBody, { title: __( 'Button', 'cohost' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Button label', 'cohost' ),
							value: attrs.label || 'Get tickets',
							onChange: function ( v ) { props.setAttributes( { label: v } ); }
						} )
					),
					el( PanelBody, { title: __( 'Event source', 'cohost' ), initialOpen: false },
						eventIdControl( props )
					)
				),
				ssr( 'cohost/event-tickets', attrs )
			);
		},
		save: function () { return null; }
	} );

} )( window.wp );
