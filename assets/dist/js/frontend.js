/**
 * Logicanvas Auctions for WooCommerce frontend: polling, bidding, host console, dashboards.
 */
(function () {
	'use strict';

	var cfg = window.wcapSettings || {};
	if (!cfg.restUrl) {
		return;
	}

	var hidden = false;
	document.addEventListener('visibilitychange', function () {
		hidden = document.hidden;
	});

	function headers() {
		return {
			'Content-Type': 'application/json',
			'X-WP-Nonce': cfg.nonce
		};
	}

	function uuid() {
		if (window.crypto && crypto.randomUUID) {
			return crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = (Math.random() * 16) | 0;
			var v = c === 'x' ? r : (r & 0x3) | 0x8;
			return v.toString(16);
		});
	}

	function request(path, options) {
		options = options || {};
		return fetch(cfg.restUrl + path, {
			method: options.method || 'GET',
			headers: headers(),
			credentials: 'same-origin',
			body: options.body ? JSON.stringify(options.body) : undefined
		}).then(function (res) {
			return res.json().then(function (body) {
				if (!res.ok) {
					var msg = (body && body.message) || cfg.i18n.error;
					throw new Error(msg);
				}
				return body;
			});
		});
	}

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function formatRemain(ms) {
		if (ms <= 0) {
			return '0:00';
		}
		var s = Math.floor(ms / 1000);
		var d = Math.floor(s / 86400);
		s -= d * 86400;
		var h = Math.floor(s / 3600);
		s -= h * 3600;
		var m = Math.floor(s / 60);
		s -= m * 60;
		if (d > 0) {
			return d + 'd ' + pad(h) + ':' + pad(m) + ':' + pad(s);
		}
		return pad(h) + ':' + pad(m) + ':' + pad(s);
	}

	function parseEndUtc(value) {
		var s = String(value || '').trim();
		if (!s) {
			return NaN;
		}
		if (/^\d{10,13}$/.test(s)) {
			var n = parseInt(s, 10);
			return n < 1e12 ? n * 1000 : n;
		}
		// Canonical plugin timestamps are UTC MySQL datetimes.
		if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/.test(s)) {
			return Date.parse(s.replace(' ', 'T') + 'Z');
		}
		// Already ISO / timezone-aware — do not append another Z.
		return Date.parse(s);
	}

	function updateCountdown(el) {
		if (!el) {
			return;
		}

		var end = parseEndUtc(el.getAttribute('data-end'));
		var serverTs = parseInt(el.getAttribute('data-server-ts') || '0', 10) * 1000;
		el._wcapEnd = end;

		// Lock clock offset once so poll latency cannot jitter the seconds.
		if (typeof el._wcapOffset !== 'number' || Number.isNaN(el._wcapOffset)) {
			el._wcapOffset = serverTs && !Number.isNaN(serverTs) ? serverTs - Date.now() : 0;
		}

		if (el.getAttribute('data-bound') === '1') {
			return;
		}

		el.setAttribute('data-bound', '1');
		var lastSpoken = '';

		function tick() {
			if (!el._wcapEnd || Number.isNaN(el._wcapEnd)) {
				el.textContent = '';
				return;
			}
			var remain = el._wcapEnd - (Date.now() + el._wcapOffset);
			var next = formatRemain(remain);
			if (el.textContent !== next) {
				el.textContent = next;
			}
			var mins = Math.floor(remain / 60000);
			if (remain > 0 && mins <= 5 && String(mins) !== lastSpoken) {
				lastSpoken = String(mins);
				el.setAttribute('aria-label', 'About ' + mins + ' minutes remaining');
			}
		}

		tick();
		if (el._wcapTimer) {
			clearInterval(el._wcapTimer);
		}
		el._wcapTimer = setInterval(tick, 1000);
	}

	function bindCountdowns(root) {
		(root || document).querySelectorAll('.wcap-countdown[data-end]').forEach(function (el) {
			updateCountdown(el);
		});
	}

	function applyState(root, state) {
		if (!state) {
			return;
		}
		var price = root.querySelector('[data-wcap-price]');
		var next = root.querySelector('[data-wcap-next]');
		var st = root.querySelector('[data-wcap-state]');
		var lead = root.querySelector('[data-wcap-lead]');
		var reserve = root.querySelector('[data-wcap-reserve]');
		var count = root.querySelector('[data-wcap-bid-count]');
		if (price && state.current_price) {
			price.textContent = state.current_price.formatted;
		}
		if (next && state.next_min_bid) {
			next.textContent = state.next_min_bid.formatted;
			var amount = root.querySelector('[name="amount"]');
			if (amount && document.activeElement !== amount) {
				amount.value = state.next_min_bid.amount;
			}
		}
		if (st) {
			st.textContent = state.state;
		}
		if (lead) {
			lead.textContent = state.is_leading ? cfg.i18n.leading : '';
		}
		if (reserve) {
			reserve.textContent = state.reserve_met ? 'Reserve met' : 'Reserve not yet met';
		}
		if (count && typeof state.bid_count !== 'undefined') {
			var tpl = state.bid_count === 1 ? (cfg.i18n.bidSingular || '%d bid placed') : (cfg.i18n.bidPlural || '%d bids placed');
			count.textContent = tpl.replace('%d', String(state.bid_count));
		}
		root.setAttribute('data-sequence', String(state.sequence || 0));
		var cd = root.querySelector('.wcap-countdown');
		if (cd && state.end_at_utc) {
			var prevEnd = cd.getAttribute('data-end') || '';
			cd.setAttribute('data-end', state.end_at_utc);
			if (state.server_ts) {
				cd.setAttribute('data-server-ts', String(state.server_ts));
			}
			// Soft-close may change end time; never spawn a second interval or reset offset.
			if (prevEnd && prevEnd !== state.end_at_utc) {
				cd._wcapEnd = parseEndUtc(state.end_at_utc);
			}
			updateCountdown(cd);
		}
		if (state.award && state.award.status === 'pending' && state.award.pay_url) {
			window.location.href = state.award.pay_url;
		}
		var prevWinner = root.getAttribute('data-winner-key') || '';
		if (state.winner_key) {
			root.setAttribute('data-winner-key', state.winner_key);
			if (prevWinner !== state.winner_key) {
				loadHistory(root.getAttribute('data-wcap-auction') || root.getAttribute('data-wcap-live'), root.querySelector('[data-wcap-history]'));
			}
		} else if (prevWinner) {
			root.removeAttribute('data-winner-key');
		}
	}

	function parseAmount(row) {
		if (!row || !row.amount) {
			return 0;
		}
		var raw = row.amount.amount != null ? row.amount.amount : row.amount;
		return parseFloat(String(raw).replace(/,/g, '')) || 0;
	}

	function buildLeaderboard(rows) {
		var map = {};
		(rows || []).forEach(function (row) {
			var key = row.bidder_key || row.bidder || String(row.id);
			var amount = parseAmount(row);
			var prev = map[key];
			if (!prev) {
				map[key] = {
					key: key,
					bidder: row.bidder || 'Bidder',
					highest: amount,
					formatted: row.amount && row.amount.formatted ? row.amount.formatted : String(amount),
					bids: 1,
					lastAt: row.created_at || ''
				};
				return;
			}
			prev.bids += 1;
			if (amount > prev.highest) {
				prev.highest = amount;
				prev.formatted = row.amount && row.amount.formatted ? row.amount.formatted : String(amount);
			}
			if ((row.created_at || '') > prev.lastAt) {
				prev.lastAt = row.created_at || '';
			}
		});
		return Object.keys(map)
			.map(function (k) {
				return map[k];
			})
			.sort(function (a, b) {
				if (b.highest !== a.highest) {
					return b.highest - a.highest;
				}
				return String(a.lastAt).localeCompare(String(b.lastAt));
			});
	}

	function loadHistory(id, target) {
		if (!target || !id) {
			return;
		}
		var body = target.querySelector('[data-wcap-leaderboard-body]');
		var recent = target.querySelector('[data-wcap-recent-bids]');
		var root = target.closest('[data-wcap-auction],[data-wcap-live]') || target;
		var winnerKey = root.getAttribute('data-winner-key') || '';

		function renderEmpty(message) {
			if (!body) {
				return;
			}
			body.innerHTML = '';
			var empty = document.createElement('tr');
			empty.className = 'wcap-leaderboard__empty';
			var emptyTd = document.createElement('td');
			emptyTd.colSpan = 5;
			emptyTd.textContent = message;
			empty.appendChild(emptyTd);
			body.appendChild(empty);
		}

		request('auctions/' + id + '/bids?per_page=50').then(function (rows) {
			if (!Array.isArray(rows)) {
				renderEmpty((cfg.i18n && cfg.i18n.error) || 'Unable to load bids.');
				return;
			}
			var board = buildLeaderboard(rows);

			if (body) {
				body.innerHTML = '';
				if (!board.length) {
					renderEmpty((cfg.i18n && cfg.i18n.noBidsYet) || 'No bids yet.');
				} else {
					board.forEach(function (row, index) {
						var isWinner = winnerKey && row.key === winnerKey;
						var isLeader = !winnerKey && index === 0;
						var tr = document.createElement('tr');
						if (isWinner) {
							tr.className = 'is-winner';
						} else if (isLeader) {
							tr.className = 'is-leader';
						}
						var rank = document.createElement('td');
						rank.setAttribute('data-label', 'Rank');
						var rankMark = document.createElement('span');
						rankMark.className = isWinner ? 'wcap-rank wcap-rank--win' : index === 0 ? 'wcap-rank wcap-rank--1' : 'wcap-rank';
						rankMark.textContent = '#' + (index + 1);
						rank.appendChild(rankMark);
						if (isWinner) {
							var win = document.createElement('span');
							win.className = 'wcap-win-badge';
							win.textContent = (cfg.i18n && cfg.i18n.winner) || 'Win';
							rank.appendChild(document.createTextNode(' '));
							rank.appendChild(win);
						} else if (isLeader) {
							var tag = document.createElement('span');
							tag.className = 'wcap-rank-tag';
							tag.textContent = (cfg.i18n && cfg.i18n.leader) || 'Leader';
							rank.appendChild(document.createTextNode(' '));
							rank.appendChild(tag);
						}
						var bidder = document.createElement('td');
						bidder.setAttribute('data-label', 'Bidder');
						bidder.textContent = row.bidder;
						if (isWinner) {
							var winInline = document.createElement('span');
							winInline.className = 'wcap-win-badge wcap-win-badge--inline';
							winInline.textContent = (cfg.i18n && cfg.i18n.winner) || 'Win';
							bidder.appendChild(document.createTextNode(' '));
							bidder.appendChild(winInline);
						}
						var highest = document.createElement('td');
						highest.setAttribute('data-label', 'Highest bid');
						highest.className = 'wcap-leaderboard__amount';
						highest.textContent = row.formatted;
						var bids = document.createElement('td');
						bids.setAttribute('data-label', 'Bids');
						bids.textContent = String(row.bids);
						var last = document.createElement('td');
						last.setAttribute('data-label', 'Last bid');
						last.className = 'wcap-leaderboard__time';
						last.textContent = row.lastAt ? String(row.lastAt).replace(' ', ' · ') + ' UTC' : '—';
						tr.appendChild(rank);
						tr.appendChild(bidder);
						tr.appendChild(highest);
						tr.appendChild(bids);
						tr.appendChild(last);
						body.appendChild(tr);
					});
				}
			} else {
				target.innerHTML = '';
				board.forEach(function (row, index) {
					var p = document.createElement('p');
					p.textContent = '#' + (index + 1) + ' ' + row.bidder + ' — ' + row.formatted;
					target.appendChild(p);
				});
			}

			if (recent) {
				recent.innerHTML = '';
				rows.slice(0, 12).forEach(function (row) {
					var li = document.createElement('li');
					var name = document.createElement('span');
					name.textContent = row.bidder || 'Bidder';
					var amount = document.createElement('strong');
					amount.textContent = row.amount && row.amount.formatted ? row.amount.formatted : '';
					li.appendChild(name);
					li.appendChild(amount);
					recent.appendChild(li);
				});
			}
		}).catch(function (err) {
			renderEmpty((err && err.message) || ((cfg.i18n && cfg.i18n.error) || 'Unable to load bids.'));
		});
	}

	function pollAuction(root, id, live) {
		var seq = parseInt(root.getAttribute('data-sequence') || '0', 10);
		var delay = live ? (cfg.pollLive || 1000) : (cfg.pollLobby || 4000);
		var errors = 0;
		var timer;

		function schedule() {
			var wait = hidden ? delay * 4 : delay * Math.pow(2, Math.min(errors, 5));
			timer = setTimeout(tick, wait);
		}

		function tick() {
			if (hidden) {
				schedule();
				return;
			}
			request('auctions/' + id + '/events?after=' + seq)
				.then(function (data) {
					errors = 0;
					var prevSeq = seq;
					if (data.state) {
						applyState(root, data.state);
						seq = data.state.sequence || seq;
					}
					if (seq !== prevSeq) {
						loadHistory(id, root.querySelector('[data-wcap-history]'));
					}
					var conn = root.querySelector('[data-wcap-connection]');
					if (conn) {
						conn.textContent = 'Live (polling)';
					}
					var act = root.querySelector('[data-wcap-activity]');
					if (act && data.events) {
						data.events.forEach(function (ev) {
							var li = document.createElement('li');
							li.textContent = ev.event_type;
							act.prepend(li);
						});
					}
					if (data.state && (data.state.state === 'live' || data.state.state === 'going_once' || data.state.state === 'going_twice')) {
						delay = cfg.pollLive || 1000;
					} else {
						delay = cfg.pollLobby || 4000;
					}
				})
				.catch(function () {
					errors += 1;
					var conn = root.querySelector('[data-wcap-connection]');
					if (conn) {
						conn.textContent = 'Reconnecting…';
					}
				})
				.finally(schedule);
		}

		tick();
		return function () {
			clearTimeout(timer);
		};
	}

	function bindBidForm(root, id) {
		var form = root.querySelector('[data-wcap-bid-form]');
		if (!form) {
			return;
		}
		var status = form.querySelector('.wcap-form-status');
		var pending = false;

		function send(amount, type) {
			if (pending) {
				return;
			}
			if (!cfg.userId) {
				status.textContent = cfg.i18n.login;
				return;
			}
			if (!window.confirm(cfg.i18n.confirmBid + ' ' + amount)) {
				return;
			}
			pending = true;
			status.textContent = '…';
			request('auctions/' + id + '/bids', {
				method: 'POST',
				body: {
					amount: amount,
					type: type || 'regular',
					idempotency_key: uuid()
				}
			})
				.then(function (res) {
					status.textContent = '';
					applyState(root, res.auction);
					loadHistory(id, root.querySelector('[data-wcap-history]'));
				})
				.catch(function (err) {
					status.textContent = err.message;
				})
				.finally(function () {
					pending = false;
				});
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var amount = form.querySelector('[name="amount"]').value;
			send(amount, 'regular');
		});

		var quick = root.querySelector('[data-wcap-quick]');
		if (quick) {
			quick.addEventListener('click', function () {
				var amount = form.querySelector('[name="amount"]').value;
				send(amount, 'quick');
			});
		}
	}

	document.querySelectorAll('[data-wcap-auction]').forEach(function (root) {
		var id = root.getAttribute('data-wcap-auction');
		bindBidForm(root, id);
		pollAuction(root, id, false);
		loadHistory(id, root.querySelector('[data-wcap-history]'));
		var watch = root.querySelector('[data-wcap-watch]');
		if (watch) {
			watch.addEventListener('click', function () {
				request('auctions/' + id + '/watch', { method: 'POST' });
			});
		}
	});

	document.querySelectorAll('[data-wcap-history][data-auction-id]').forEach(function (el) {
		if (el.closest('[data-wcap-auction],[data-wcap-live]')) {
			return;
		}
		var id = el.getAttribute('data-auction-id');
		if (id) {
			loadHistory(id, el);
		}
	});

	document.querySelectorAll('[data-wcap-live]').forEach(function (root) {
		var id = root.getAttribute('data-wcap-live');
		bindBidForm(root, id);
		pollAuction(root, id, true);
		if (cfg.userId) {
			setInterval(function () {
				if (!hidden) {
					request('live/' + id + '/join', { method: 'POST', body: {} }).catch(function () {});
				}
			}, 10000);
		}
	});

	document.querySelectorAll('[data-wcap-host]').forEach(function (root) {
		var id = root.getAttribute('data-wcap-host');
		pollAuction(root, id, true);
		root.querySelectorAll('[data-host-action]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var action = btn.getAttribute('data-host-action');
				var reason = (root.querySelector('[data-host-reason]') || {}).value || '';
				if ((action === 'cancel' || action === 'unsold' || action === 'sell') && !window.confirm(btn.textContent + '?')) {
					return;
				}
				request('live/' + id + '/host', {
					method: 'POST',
					body: { action: action, reason: reason, seconds: action === 'extend' ? 30 : undefined }
				})
					.then(function () {
						root.querySelector('.wcap-form-status').textContent = 'OK';
					})
					.catch(function (err) {
						root.querySelector('.wcap-form-status').textContent = err.message;
					});
			});
		});
	});

	function mediaItem(attachment) {
		var json = attachment.toJSON ? attachment.toJSON() : attachment;
		var thumb = json.url;
		if (json.sizes) {
			if (json.sizes.medium && json.sizes.medium.url) {
				thumb = json.sizes.medium.url;
			} else if (json.sizes.thumbnail && json.sizes.thumbnail.url) {
				thumb = json.sizes.thumbnail.url;
			}
		}
		return { id: json.id, url: json.url, thumb: thumb };
	}

	function openMediaLibrary(options) {
		if (!window.wp || !wp.media) {
			return Promise.reject(new Error((cfg.i18n && cfg.i18n.mediaMissing) || 'Media library unavailable.'));
		}
		return new Promise(function (resolve) {
			var frame = wp.media({
				title: options.title,
				button: { text: options.button },
				library: { type: 'image' },
				multiple: !!options.multiple
			});
			frame.on('select', function () {
				var selection = frame.state().get('selection');
				resolve(selection.map(mediaItem));
			});
			frame.open();
		});
	}

	function addThumb(container, item, onRemove) {
		var wrap = document.createElement('div');
		wrap.className = 'wcap-thumb';
		var img = document.createElement('img');
		img.src = item.thumb || item.url;
		img.alt = '';
		wrap.appendChild(img);
		if (onRemove) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.setAttribute('aria-label', 'Remove');
			btn.textContent = '×';
			btn.addEventListener('click', function () {
				wrap.remove();
				onRemove();
			});
			wrap.appendChild(btn);
		}
		container.appendChild(wrap);
	}

	function bindSubmitForm(submit) {
		function setStatus(msg) {
			var el = submit.querySelector('.wcap-form-status');
			if (el) {
				el.textContent = msg || '';
			}
		}

		function sourceValue() {
			var checked = submit.querySelector('input[name="product_source"]:checked');
			if (checked) {
				return checked.value;
			}
			var hidden = submit.querySelector('input[name="product_source"]');
			return hidden && hidden.value ? hidden.value : 'new';
		}

		function syncSource() {
			var source = sourceValue();
			submit.querySelectorAll('[data-wcap-source-panel]').forEach(function (el) {
				el.hidden = el.getAttribute('data-wcap-source-panel') !== source;
			});
			var title = submit.querySelector('[name="title"]');
			if (title) {
				title.required = source === 'new';
			}
		}

		submit.querySelectorAll('input[name="product_source"]').forEach(function (el) {
			el.addEventListener('change', syncSource);
		});
		syncSource();

		var search = submit.querySelector('[data-wcap-product-search]');
		var results = submit.querySelector('[data-wcap-product-results]');
		var selected = submit.querySelector('[data-wcap-selected-product]');
		var productId = submit.querySelector('[name="product_id"]');
		var featuredInput = submit.querySelector('[name="featured_image_id"]');
		var galleryInput = submit.querySelector('[name="gallery_ids"]');
		var featuredPreview = submit.querySelector('[data-wcap-featured-preview]');
		var galleryPreview = submit.querySelector('[data-wcap-gallery-preview]');
		var galleryIds = [];
		var searchTimer;
		var auctionId = parseInt(submit.getAttribute('data-auction-id') || '0', 10) || 0;

		function setGallery() {
			if (galleryInput) {
				galleryInput.value = galleryIds.join(',');
			}
		}

		if (galleryInput && galleryInput.value) {
			galleryIds = galleryInput.value
				.split(/[\s,]+/)
				.map(function (s) {
					return parseInt(s, 10);
				})
				.filter(function (n) {
					return n > 0;
				});
		}

		function wireExistingThumbs(container, isGallery) {
			if (!container) {
				return;
			}
			container.querySelectorAll('[data-wcap-thumb-id]').forEach(function (wrap) {
				var id = parseInt(wrap.getAttribute('data-wcap-thumb-id'), 10);
				var btn = wrap.querySelector('button');
				if (!btn || !id) {
					return;
				}
				btn.addEventListener('click', function () {
					wrap.remove();
					if (isGallery) {
						galleryIds = galleryIds.filter(function (gid) {
							return gid !== id;
						});
						setGallery();
					} else if (featuredInput) {
						featuredInput.value = '';
					}
				});
			});
		}
		wireExistingThumbs(featuredPreview, false);
		wireExistingThumbs(galleryPreview, true);

		function pickProduct(item) {
			if (!productId) {
				return;
			}
			productId.value = String(item.id);
			if (selected) {
				selected.textContent = item.name + (item.sku ? ' (' + item.sku + ')' : '');
			}
			if (results) {
				results.hidden = true;
				results.innerHTML = '';
			}
			var title = submit.querySelector('[name="title"]');
			var price = submit.querySelector('[name="starting_price"]');
			if (title && !title.value) {
				title.value = item.name;
			}
			if (price && item.price) {
				price.value = item.price;
			}
		}

		if (search && results) {
			search.addEventListener('input', function () {
				clearTimeout(searchTimer);
				searchTimer = setTimeout(function () {
					var q = search.value.trim();
					if (q.length < 2) {
						results.hidden = true;
						return;
					}
					request('products?search=' + encodeURIComponent(q))
						.then(function (rows) {
							results.innerHTML = '';
							if (!rows.length) {
								results.hidden = true;
								return;
							}
							rows.forEach(function (row) {
								var li = document.createElement('li');
								li.textContent = row.name + (row.sku ? ' — ' + row.sku : '') + (row.in_use ? ' (already in an auction)' : '');
								if (row.in_use) {
									li.setAttribute('aria-disabled', 'true');
								} else {
									li.tabIndex = 0;
									li.addEventListener('click', function () {
										pickProduct(row);
									});
								}
								results.appendChild(li);
							});
							results.hidden = false;
						})
						.catch(function () {
							results.hidden = true;
						});
				}, 250);
			});
		}

		var featuredBtn = submit.querySelector('[data-wcap-featured-media]');
		if (featuredBtn && featuredInput && featuredPreview) {
			featuredBtn.addEventListener('click', function () {
				openMediaLibrary({
					title: (cfg.i18n && cfg.i18n.selectFeatured) || 'Select featured image',
					button: (cfg.i18n && cfg.i18n.useImage) || 'Use this image',
					multiple: false
				})
					.then(function (items) {
						var item = items[0];
						if (!item) {
							return;
						}
						featuredInput.value = String(item.id);
						featuredPreview.innerHTML = '';
						addThumb(featuredPreview, item, function () {
							featuredInput.value = '';
						});
					})
					.catch(function (err) {
						setStatus(err.message);
					});
			});
		}

		var galleryBtn = submit.querySelector('[data-wcap-gallery-media]');
		if (galleryBtn && galleryInput && galleryPreview) {
			galleryBtn.addEventListener('click', function () {
				openMediaLibrary({
					title: (cfg.i18n && cfg.i18n.selectGallery) || 'Select gallery images',
					button: (cfg.i18n && cfg.i18n.useImages) || 'Use these images',
					multiple: true
				})
					.then(function (items) {
						items.forEach(function (item) {
							if (galleryIds.length >= 12 || galleryIds.indexOf(item.id) !== -1) {
								return;
							}
							galleryIds.push(item.id);
							addThumb(galleryPreview, item, function () {
								galleryIds = galleryIds.filter(function (id) {
									return id !== item.id;
								});
								setGallery();
							});
						});
						setGallery();
					})
					.catch(function (err) {
						setStatus(err.message);
					});
			});
		}

		submit.addEventListener('submit', function (e) {
			e.preventDefault();
			if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
				window.tinymce.triggerSave();
			}
			var intent = (e.submitter && e.submitter.value) || 'submit';
			var fd = new FormData(submit);
			var body = {};
			fd.forEach(function (v, k) {
				if (k === 'intent') {
					return;
				}
				body[k] = v;
			});
			if (sourceValue() === 'existing' && !body.product_id) {
				setStatus('Select an existing product, or switch to create a new one.');
				return;
			}
			if (sourceValue() === 'new' && !body.title) {
				setStatus('Enter a title for the new product.');
				return;
			}
			setStatus('Saving…');
			var endpoint = auctionId ? 'auctions/' + auctionId : 'auctions';
			var method = auctionId ? 'PUT' : 'POST';
			request(endpoint, { method: method, body: body })
				.then(function (auction) {
					function listingsUrl() {
						try {
							var url = new URL(window.location.href);
							url.searchParams.set('wcap_view', 'listings');
							url.searchParams.delete('auction_id');
							url.hash = '';
							return url.toString();
						} catch (e) {
							return window.location.pathname + '?wcap_view=listings';
						}
					}
					var id = auctionId || (auction && auction.id);
					if (auctionId) {
						if (intent === 'publish' && id) {
							return request('auctions/' + id + '/approve', { method: 'POST', body: {} }).then(function () {
								setStatus('Updated.');
								window.location.href = listingsUrl();
								return auction;
							});
						}
						if (intent === 'submit' && id) {
							return request('auctions/' + id + '/submit', { method: 'POST', body: {} }).then(function () {
								setStatus('Submitted for review.');
								window.location.href = listingsUrl();
								return auction;
							});
						}
						setStatus('Saved.');
						window.location.href = listingsUrl();
						return auction;
					}
					if (intent === 'draft') {
						setStatus('Draft saved.');
						window.location.href = listingsUrl();
						return auction;
					}
					if (intent === 'publish') {
						return request('auctions/' + auction.id + '/approve', { method: 'POST', body: {} }).then(function (published) {
							setStatus('Published.');
							if (published.permalink) {
								window.location.href = published.permalink;
							} else {
								window.location.href = listingsUrl();
							}
							return published;
						});
					}
					return request('auctions/' + auction.id + '/submit', { method: 'POST', body: {} }).then(function () {
						setStatus('Submitted for review.');
						window.location.href = listingsUrl();
						return auction;
					});
				})
				.catch(function (err) {
					setStatus(err.message);
				});
		});
	}

	document.querySelectorAll('[data-wcap-submit]').forEach(bindSubmitForm);

	document.querySelectorAll('[data-wcap-auction-action]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var id = btn.getAttribute('data-id');
			var action = btn.getAttribute('data-wcap-auction-action');
			var path = action === 'approve' ? 'auctions/' + id + '/approve' : 'auctions/' + id + '/submit';
			btn.disabled = true;
			request(path, { method: 'POST', body: {} })
				.then(function () {
					window.location.reload();
				})
				.catch(function (err) {
					btn.disabled = false;
					window.alert(err.message);
				});
		});
	});

	document.querySelectorAll('[data-wcap-accept-bid]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var id = btn.getAttribute('data-id');
			if (!id) {
				return;
			}
			var confirmMsg = (cfg.i18n && cfg.i18n.acceptBid) || 'Sell to the current highest bidder now?';
			if (!window.confirm(confirmMsg)) {
				return;
			}
			btn.disabled = true;
			var wrap = btn.closest('.wcap-accept-card, .wcap-holder-sell, .active-lot, tr, .wcap-bid-panel') || btn.parentElement;
			var status = (wrap && wrap.querySelector('.wcap-form-status')) || document.querySelector('[data-wcap-accept-status]');
			if (status) {
				status.textContent = '…';
			}
			request('auctions/' + id + '/accept-bid', { method: 'POST', body: {} })
				.then(function () {
					if (status) {
						status.textContent = (cfg.i18n && cfg.i18n.acceptOk) || 'Highest bid accepted.';
					}
					window.location.reload();
				})
				.catch(function (err) {
					btn.disabled = false;
					if (status) {
						status.textContent = err.message;
					} else {
						window.alert(err.message);
					}
				});
		});
	});

	var apply = document.querySelector('[data-wcap-apply]');
	if (apply) {
		apply.addEventListener('submit', function (e) {
			e.preventDefault();
			var fd = new FormData(apply);
			request('holder/apply', {
				method: 'POST',
				body: { company: fd.get('company'), notes: fd.get('notes') }
			})
				.then(function () {
					apply.querySelector('.wcap-form-status').textContent = 'Application submitted.';
				})
				.catch(function (err) {
					apply.querySelector('.wcap-form-status').textContent = err.message;
				});
		});
	}

	var pay = document.querySelector('[data-wcap-pay]');
	if (pay && !document.querySelector('.wcap-checkout-start-form')) {
		pay.addEventListener('click', function () {
			var wrap = pay.closest('[data-award-id]');
			var id = wrap.getAttribute('data-award-id');
			request('awards/' + id + '/checkout', { method: 'POST', body: {} })
				.then(function (res) {
					if (res && res.checkout_url) {
						window.location.href = res.checkout_url;
					}
				})
				.catch(function (err) {
					var status = wrap.querySelector('.wcap-form-status');
					if (status) {
						status.textContent = err.message;
					}
				});
		});
	}

	document.querySelectorAll('[data-wcap-order-form]').forEach(function (form) {
		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			var orderId = form.querySelector('[name="order_id"]');
			if (!orderId || !orderId.value) {
				return;
			}
			var statusEl = form.querySelector('.wcap-form-status');
			var btn = form.querySelector('button[type="submit"]');
			var body = {
				status: (form.querySelector('[name="status"]') || {}).value || '',
				tracking_number: (form.querySelector('[name="tracking_number"]') || {}).value || '',
				tracking_carrier: (form.querySelector('[name="tracking_carrier"]') || {}).value || '',
				tracking_url: (form.querySelector('[name="tracking_url"]') || {}).value || '',
				fulfilment_note: (form.querySelector('[name="fulfilment_note"]') || {}).value || '',
				customer_note: (form.querySelector('[name="customer_note"]') || {}).value || '',
				mark_paid_offline: !!(form.querySelector('[name="mark_paid_offline"]') || {}).checked
			};
			if (btn) {
				btn.disabled = true;
			}
			if (statusEl) {
				statusEl.textContent = '…';
			}
			request('orders/' + orderId.value, { method: 'POST', body: body })
				.then(function () {
					if (statusEl) {
						statusEl.textContent = (cfg.i18n && cfg.i18n.orderUpdated) || 'Order updated.';
					}
					window.location.reload();
				})
				.catch(function (err) {
					if (btn) {
						btn.disabled = false;
					}
					if (statusEl) {
						statusEl.textContent = err.message;
					}
				});
		});
	});

	(function bindPayouts() {
		var root = document.querySelector('[data-wcap-payouts]');
		if (!root) {
			return;
		}
		var methodSelect = root.querySelector('[data-wcap-method-select]');
		function syncMethodPanels() {
			var value = methodSelect ? methodSelect.value : '';
			root.querySelectorAll('[data-wcap-method-fields]').forEach(function (panel) {
				panel.hidden = panel.getAttribute('data-wcap-method-fields') !== value;
			});
		}
		if (methodSelect) {
			methodSelect.addEventListener('change', syncMethodPanels);
			syncMethodPanels();
		}

		var methodForm = document.querySelector('[data-wcap-payout-method]');
		if (methodForm) {
			methodForm.addEventListener('submit', function (ev) {
				ev.preventDefault();
				var statusEl = methodForm.querySelector('.wcap-form-status');
				var btn = methodForm.querySelector('button[type="submit"]');
				var body = {
					method: (methodForm.querySelector('[name="method"]') || {}).value || '',
					account_name: (methodForm.querySelector('[name="account_name"]') || {}).value || '',
					bank_name: (methodForm.querySelector('[name="bank_name"]') || {}).value || '',
					account_number: (methodForm.querySelector('[name="account_number"]') || {}).value || '',
					routing: (methodForm.querySelector('[name="routing"]') || {}).value || '',
					paypal_email: (methodForm.querySelector('[name="paypal_email"]') || {}).value || '',
					other_details: (methodForm.querySelector('[name="other_details"]') || {}).value || ''
				};
				if (btn) {
					btn.disabled = true;
				}
				if (statusEl) {
					statusEl.textContent = '…';
				}
				request('payouts/method', { method: 'POST', body: body })
					.then(function () {
						if (statusEl) {
							statusEl.textContent = (cfg.i18n && cfg.i18n.methodSaved) || 'Payment method saved.';
						}
						window.location.reload();
					})
					.catch(function (err) {
						if (btn) {
							btn.disabled = false;
						}
						if (statusEl) {
							statusEl.textContent = err.message;
						}
					});
			});
		}

		var requestForm = document.querySelector('[data-wcap-payout-request]');
		if (requestForm) {
			requestForm.addEventListener('submit', function (ev) {
				ev.preventDefault();
				var statusEl = requestForm.querySelector('.wcap-form-status');
				var btn = requestForm.querySelector('button[type="submit"]');
				var amount = (requestForm.querySelector('[name="amount"]') || {}).value || '';
				var note = (requestForm.querySelector('[name="note"]') || {}).value || '';
				if (btn) {
					btn.disabled = true;
				}
				if (statusEl) {
					statusEl.textContent = '…';
				}
				request('payouts', { method: 'POST', body: { amount: amount, note: note } })
					.then(function () {
						if (statusEl) {
							statusEl.textContent = (cfg.i18n && cfg.i18n.payoutRequested) || 'Payout requested.';
						}
						window.location.reload();
					})
					.catch(function (err) {
						if (btn) {
							btn.disabled = false;
						}
						if (statusEl) {
							statusEl.textContent = err.message;
						}
					});
			});
		}

		document.querySelectorAll('[data-wcap-payout-cancel]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = btn.getAttribute('data-id');
				if (!id) {
					return;
				}
				btn.disabled = true;
				request('payouts/' + id + '/cancel', { method: 'POST', body: {} })
					.then(function () {
						window.location.reload();
					})
					.catch(function (err) {
						btn.disabled = false;
						window.alert(err.message);
					});
			});
		});
	})();

	function fillDash() {
		var bidder = document.querySelector('[data-wcap-bidder-dash]');
		if (!bidder || bidder.getAttribute('data-wcap-ssr') === '1') {
			return;
		}
		request('me/dashboard')
			.then(function (data) {
				var lead = bidder.querySelector('[data-wcap-leading]');
				(data.leading || []).forEach(function (row) {
					var p = document.createElement('p');
					p.textContent = '#' + row.auction_id + ' @ ' + row.current_amount + ' (' + row.state + ')';
					lead.appendChild(p);
				});
				var awards = bidder.querySelector('[data-wcap-awards]');
				(data.awards || []).forEach(function (row) {
					var p = document.createElement('p');
					p.textContent = '#' + row.auction_id + ' ' + row.amount + ' — ' + row.status;
					if (row.status === 'pending') {
						var a = document.createElement('a');
						a.href = '?award_id=' + row.id;
						a.textContent = ' Pay';
						p.appendChild(a);
					}
					awards.appendChild(p);
				});
			})
			.catch(function () {
				var p = document.createElement('p');
				p.className = 'wcap-empty';
				p.textContent = (window.wcapSettings && wcapSettings.i18n && wcapSettings.i18n.error) || 'Unable to load dashboard data.';
				bidder.appendChild(p);
			});
	}

	function bindGalleries(root) {
		(root || document).querySelectorAll('[data-wcap-gallery]').forEach(function (gallery) {
			if (gallery.getAttribute('data-bound')) {
				return;
			}
			gallery.setAttribute('data-bound', '1');

			var main = gallery.querySelector('[data-wcap-gallery-main]');
			var hero = gallery.querySelector('[data-wcap-gallery-hero]');
			var thumbs = Array.prototype.slice.call(gallery.querySelectorAll('[data-wcap-gallery-thumb]'));
			if (!main || !thumbs.length) {
				return;
			}

			var urls = thumbs.map(function (btn) {
				return btn.getAttribute('data-full') || '';
			}).filter(Boolean);
			var index = 0;

			function setActive(i) {
				if (i < 0 || i >= thumbs.length) {
					return;
				}
				index = i;
				var btn = thumbs[i];
				var full = btn.getAttribute('data-full') || '';
				var thumbImg = btn.querySelector('img');
				if (full) {
					main.src = full;
					if (hero) {
						hero.setAttribute('data-full', full);
					}
				}
				if (thumbImg && thumbImg.getAttribute('alt')) {
					main.alt = thumbImg.getAttribute('alt');
				}
				thumbs.forEach(function (el, n) {
					var on = n === i;
					el.classList.toggle('is-active', on);
					el.setAttribute('aria-pressed', on ? 'true' : 'false');
				});
			}

			function ensureLightbox() {
				var box = document.getElementById('wcap-lightbox');
				if (box) {
					return box;
				}
				box = document.createElement('div');
				box.id = 'wcap-lightbox';
				box.className = 'wcap-lightbox';
				box.hidden = true;
				box.setAttribute('role', 'dialog');
				box.setAttribute('aria-modal', 'true');
				box.setAttribute('aria-label', 'Photo preview');
				box.innerHTML =
					'<div class="wcap-lightbox__inner">' +
					'<button type="button" class="wcap-lightbox__close" data-wcap-lightbox-close aria-label="Close">&times;</button>' +
					'<button type="button" class="wcap-lightbox__nav wcap-lightbox__nav--prev" data-wcap-lightbox-prev aria-label="Previous photo">‹</button>' +
					'<img src="" alt="" data-wcap-lightbox-img />' +
					'<button type="button" class="wcap-lightbox__nav wcap-lightbox__nav--next" data-wcap-lightbox-next aria-label="Next photo">›</button>' +
					'</div>';
				document.body.appendChild(box);
				return box;
			}

			function showLightbox(start) {
				if (!urls.length) {
					return;
				}
				index = Math.max(0, Math.min(start, urls.length - 1));
				var box = ensureLightbox();
				var img = box.querySelector('[data-wcap-lightbox-img]');
				var prev = box.querySelector('[data-wcap-lightbox-prev]');
				var next = box.querySelector('[data-wcap-lightbox-next]');
				function render() {
					img.src = urls[index];
					img.alt = (thumbs[index] && thumbs[index].querySelector('img') && thumbs[index].querySelector('img').alt) || '';
					setActive(index);
					if (prev) {
						prev.hidden = urls.length < 2;
					}
					if (next) {
						next.hidden = urls.length < 2;
					}
				}
				function close() {
					box.hidden = true;
					document.body.style.overflow = '';
					document.removeEventListener('keydown', onKey);
				}
				function onKey(e) {
					if (e.key === 'Escape') {
						close();
					} else if (e.key === 'ArrowLeft') {
						index = (index - 1 + urls.length) % urls.length;
						render();
					} else if (e.key === 'ArrowRight') {
						index = (index + 1) % urls.length;
						render();
					}
				}
				box.onclick = function (e) {
					if (e.target === box || e.target.closest('[data-wcap-lightbox-close]')) {
						close();
					} else if (e.target.closest('[data-wcap-lightbox-prev]')) {
						index = (index - 1 + urls.length) % urls.length;
						render();
					} else if (e.target.closest('[data-wcap-lightbox-next]')) {
						index = (index + 1) % urls.length;
						render();
					}
				};
				document.addEventListener('keydown', onKey);
				render();
				box.hidden = false;
				document.body.style.overflow = 'hidden';
			}

			thumbs.forEach(function (btn, i) {
				btn.addEventListener('click', function () {
					if (btn.classList.contains('is-active')) {
						showLightbox(i);
						return;
					}
					setActive(i);
				});
			});

			if (hero) {
				hero.addEventListener('click', function () {
					var current = hero.getAttribute('data-full') || main.src;
					var i = urls.indexOf(current);
					showLightbox(i >= 0 ? i : index);
				});
			}
		});
	}


	function bindQrCodes(root) {
		if (typeof QRCode === 'undefined') {
			return;
		}
		(root || document).querySelectorAll('[data-wcap-qr]').forEach(function (panel) {
			if (panel.getAttribute('data-qr-bound')) {
				return;
			}
			panel.setAttribute('data-qr-bound', '1');
			var shareUrl = panel.getAttribute('data-share-url') || '';
			var details = panel.getAttribute('data-details') || '';
			var shareEl = panel.querySelector('[data-wcap-qr-share]');
			var detailsEl = panel.querySelector('[data-wcap-qr-details]');
			var level = QRCode.CorrectLevel ? QRCode.CorrectLevel.M : 0;
			var levelLow = QRCode.CorrectLevel ? QRCode.CorrectLevel.L : 0;
			if (shareEl && shareUrl) {
				shareEl.innerHTML = '';
				new QRCode(shareEl, { text: shareUrl, width: 120, height: 120, correctLevel: level });
			}
			if (detailsEl && details) {
				detailsEl.innerHTML = '';
				new QRCode(detailsEl, { text: details, width: 120, height: 120, correctLevel: levelLow });
			}
		});
	}

	function bindLotTabs(root) {
		(root || document).querySelectorAll('[data-wcap-lot-tabs]').forEach(function (wrap) {
			if (wrap.getAttribute('data-bound')) {
				return;
			}
			wrap.setAttribute('data-bound', '1');

			var tabs = Array.prototype.slice.call(wrap.querySelectorAll('[data-wcap-tab]'));
			var panels = Array.prototype.slice.call(wrap.querySelectorAll('[data-wcap-tab-panel]'));

			function activate(name) {
				tabs.forEach(function (tab) {
					var on = tab.getAttribute('data-wcap-tab') === name;
					tab.classList.toggle('is-active', on);
					tab.setAttribute('aria-selected', on ? 'true' : 'false');
				});
				panels.forEach(function (panel) {
					var on = panel.getAttribute('data-wcap-tab-panel') === name;
					panel.classList.toggle('is-active', on);
					panel.hidden = !on;
					if (on && name === 'share') {
						bindQrCodes(panel);
					}
				});
			}

			tabs.forEach(function (tab) {
				tab.addEventListener('click', function () {
					activate(tab.getAttribute('data-wcap-tab') || 'details');
				});
			});

			wrap.querySelectorAll('[data-wcap-copy-share]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var input = wrap.querySelector('#wcap-share-url');
					if (!input || !input.value) {
						return;
					}
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(input.value).then(function () {
							btn.textContent = (cfg.i18n && cfg.i18n.copied) || 'Copied';
							setTimeout(function () {
								btn.textContent = (cfg.i18n && cfg.i18n.copy) || 'Copy';
							}, 1600);
						});
						return;
					}
					input.select();
					document.execCommand('copy');
				});
			});

			var hash = (window.location.hash || '').replace(/^#/, '');
			if (hash && tabs.some(function (t) { return t.getAttribute('data-wcap-tab') === hash; })) {
				activate(hash);
			}
		});
	}

	bindCountdowns(document);
	bindGalleries(document);
	bindLotTabs(document);
	fillDash();
})();
