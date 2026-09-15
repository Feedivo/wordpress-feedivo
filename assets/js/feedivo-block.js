(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) {
		return;
	}

	var el = wp.element.createElement;
	var useRef = wp.element.useRef;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var components = wp.components;
	var ServerSideRender = wp.serverSideRender;

	var data = window.feedivoBlock || {};
	var feeds = Array.isArray(data.feeds) ? data.feeds : [];

	function feedOptions() {
		var list = [{ value: '', label: __('— Select a feed —', 'feedivo') }];
		feeds.forEach(function (feed) {
			list.push({ value: feed.uuid, label: feed.name });
		});
		return list;
	}

	function isKnown(uuid) {
		return feeds.some(function (feed) {
			return feed.uuid === uuid;
		});
	}

	function enhance(node) {
		if (!node) {
			return;
		}
		var doc = node.ownerDocument;
		var view = doc && doc.defaultView;
		if (!view) {
			return;
		}

		Array.prototype.forEach.call(node.querySelectorAll('.feed-layout'), function (layout) {
			layout.setAttribute('data-feed-autoplay', 'off');
		});

		if (view.FeedivoFrontend) {
			view.FeedivoFrontend.initMasonry(node);
			view.FeedivoFrontend.initCarousels(node);
			return;
		}

		if (!data.frontendScript) {
			return;
		}

		var pending = doc.getElementById('feedivo-preview-script');
		if (pending) {
			pending.addEventListener('load', function () {
				enhance(node);
			});
			return;
		}

		var script = doc.createElement('script');
		script.id = 'feedivo-preview-script';
		script.src = data.frontendScript;
		script.onload = function () {
			enhance(node);
		};
		(doc.head || doc.body).appendChild(script);
	}

	function Edit(props) {
		var uuid = props.attributes.feedUuid || '';
		var ref = useRef(null);
		var blockProps = wp.blockEditor.useBlockProps({ ref: ref });

		useEffect(function () {
			var node = ref.current;
			if (!node || !window.MutationObserver) {
				return undefined;
			}

			var observer = new window.MutationObserver(function () {
				observer.disconnect();
				enhance(node);
				observer.observe(node, { childList: true, subtree: true });
			});
			enhance(node);
			observer.observe(node, { childList: true, subtree: true });

			return function () {
				observer.disconnect();
			};
		}, [uuid]);

		var selector = el(components.SelectControl, {
			label: __('Feed', 'feedivo'),
			value: uuid,
			options: feedOptions(),
			onChange: function (value) {
				props.setAttributes({ feedUuid: value });
			},
			help: __('Choose the design and content for this feed in Feedivo.', 'feedivo'),
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true
		});

		var inspector = el(
			wp.blockEditor.InspectorControls,
			null,
			el(components.PanelBody, { title: __('Feed', 'feedivo') }, selector)
		);

		if (!feeds.length) {
			return el(
				'div',
				blockProps,
				el(
					components.Placeholder,
					{
						icon: 'format-gallery',
						label: __('Feedivo Feed', 'feedivo'),
						instructions: __('Connect this website to Feedivo to choose one of your social media feeds.', 'feedivo')
					},
					data.settingsUrl
						? el(
								components.Button,
								{ variant: 'primary', href: data.settingsUrl },
								__('Open Feedivo settings', 'feedivo')
						  )
						: null
				)
			);
		}

		var missing = '' !== uuid && !isKnown(uuid);

		if ('' === uuid || missing) {
			return el(
				'div',
				blockProps,
				inspector,
				el(
					components.Placeholder,
					{
						icon: 'format-gallery',
						label: __('Feedivo Feed', 'feedivo'),
						instructions: missing
							? __('This feed is not available. Update your content or choose another feed.', 'feedivo')
							: __('Choose a social media feed to display.', 'feedivo')
					},
					selector
				)
			);
		}

		if (!ServerSideRender) {
			return el('div', blockProps, inspector, el(components.Spinner, null));
		}

		return el(
			'div',
			blockProps,
			inspector,

			el(
				components.Disabled,
				null,
				el(ServerSideRender, {
					block: 'feedivo/feed',
					attributes: { feedUuid: uuid }
				})
			)
		);
	}

	wp.blocks.registerBlockType('feedivo/feed', {
		edit: Edit,
		save: function () {
			return null;
		}
	});
})(window.wp);
