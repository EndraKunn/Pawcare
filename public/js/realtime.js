
(function () {
    'use strict';

    var meta = function (name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.content : '';
    };

    var key = meta('reverb-key');
    var host = meta('reverb-host') || window.location.hostname;
    var port = parseInt(meta('reverb-port') || '8080', 10);
    var scheme = meta('reverb-scheme') || 'http';

    var userId = meta('realtime-user-id');
    var businessId = meta('realtime-business-id');
    var conversationId = meta('realtime-conversation-id');

    var EchoCtor = window.Echo && (window.Echo.default || window.Echo);

    if (window.Echo && typeof window.Echo.socketId !== 'function') { window.Echo.socketId = function () { return undefined; }; }
    if (!key || typeof EchoCtor !== 'function') return;

    var csrf = meta('csrf-token');

    var echo = new EchoCtor({
        broadcaster: 'reverb',
        key: key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {}
        }
    });

    window.PawCareEcho = echo;
    window.Echo = echo;

    // Kelas warna status booking
    var statusClasses = {
        'Menunggu': 'wait',
        'Dikonfirmasi': 'conf',
        'Dalam Proses': 'prog',
        'Selesai': 'done',
        'Dibatalkan': 'bad'
    };

    // Memperbarui badge status
    function updateStatusBadge(element, status) {
        if (!element) return;

        element.textContent = status;

        [
            'wait',
            'conf',
            'prog',
            'done',
            'bad',
            'gray'
        ].forEach(function (cls) {
            element.classList.remove(cls);
        });

        element.classList.add(statusClasses[status] || 'gray');
    }

    // Memperbarui statistik Care Board
    function updateCareBoardStats() {
        document.querySelectorAll('[data-care-column]').forEach(function (column) {
            var list = column.querySelector('[data-care-items]');
            var count = column.querySelector('[data-care-count]');

            var total = list
                ? list.querySelectorAll('[data-realtime-booking-id]').length
                : 0;

            if (count) count.textContent = total;

            if (list && total === 0 && !list.querySelector('[data-care-empty]')) {
                var empty = document.createElement('p');

                empty.className = 'sm mut';
                empty.setAttribute('data-care-empty', '');
                empty.style.margin = '14px 0 0';
                empty.textContent = 'Belum ada booking pada tahap ini.';

                list.appendChild(empty);
            }
        });

        var waiting = document.querySelector(
            '[data-care-column="Menunggu"] [data-care-count]'
        );

        var confirmed = document.querySelector(
            '[data-care-column="Dikonfirmasi"] [data-care-count]'
        );

        var progress = document.querySelector(
            '[data-care-column="Dalam Proses"] [data-care-count]'
        );

        var statWaiting = document.querySelector('[data-care-stat="waiting"]');
        var statConfirmed = document.querySelector('[data-care-stat="confirmed"]');
        var statProgress = document.querySelector('[data-care-stat="progress"]');

        if (waiting && statWaiting) {
            statWaiting.textContent = waiting.textContent;
        }

        if (confirmed && statConfirmed) {
            statConfirmed.textContent = confirmed.textContent;
        }

        if (progress && statProgress) {
            statProgress.textContent = progress.textContent;
        }
    }

    // Memperbarui timeline status Customer
    function updateStatusTimeline(data) {
        var log = document.querySelector('[data-realtime-status-log]');

        if (!log) return;

        var previous = log.querySelector('li.now');

        if (previous) {
            previous.classList.remove('now');
            previous.classList.add('ok');

            var previousDot = previous.querySelector('.dot');

            if (previousDot) {
                previousDot.textContent = '✓';
            }
        }

        var entry = document.createElement('li');
        entry.className = 'now';

        var dot = document.createElement('span');
        dot.className = 'dot';
        dot.textContent = '●';

        var title = document.createElement('b');
        title.textContent = data.status || 'Status diperbarui';

        var detail = document.createElement('small');

        var stamp = data.changed_at
            ? new Date(data.changed_at).toLocaleString('id-ID')
            : new Date().toLocaleString('id-ID');

        detail.textContent = stamp + (data.note ? ' · ' + data.note : '');

        entry.appendChild(dot);
        entry.appendChild(title);
        entry.appendChild(detail);

        log.appendChild(entry);
    }

    // EVENT: BOOKING STATUS UPDATED
    function updateBooking(data) {
        if (!data || !data.booking_id || !data.status) return;

        var selector =
            '[data-realtime-booking-id="' + data.booking_id + '"]';

        // Perbarui badge Customer dan Owner
        document.querySelectorAll(selector).forEach(function (card) {
            var label = card.querySelector('[data-realtime-status]');

            if (label) {
                updateStatusBadge(label, data.status);
            }

            // Pindahkan kartu Care Board ke kolom status baru
            var oldColumn = card.closest('[data-care-column]');

            if (oldColumn) {
                var targetColumn = document.querySelector(
                    '[data-care-column="' + data.status + '"]'
                );

                if (targetColumn) {
                    var targetList = targetColumn.querySelector('[data-care-items]');

                    if (targetList) {
                        var empty = targetList.querySelector('[data-care-empty]');

                        if (empty) empty.remove();

                        targetList.prepend(card);
                    }
                } else {
                    card.remove();
                }
            }
        });

        // Perbarui jumlah booking Care Board
        updateCareBoardStats();

        // Perbarui timeline Customer
        updateStatusTimeline(data);

        // Kirim event internal untuk komponen lain
        document.dispatchEvent(
            new CustomEvent('pawcare:booking-status-updated', {
                detail: data
            })
        );
    }

    // EVENT: BOOKING CREATED
    function appendBooking(data) {
        if (
            !data ||
            !data.booking_id ||
            document.querySelector(
                '[data-realtime-booking-id="' + data.booking_id + '"]'
            )
        ) {
            return;
        }

        var column = document.querySelector(
            '[data-care-column="Menunggu"] [data-care-items]'
        );

        if (!column) return;

        var empty = column.querySelector('[data-care-empty]');

        if (empty) empty.remove();

        var card = document.createElement('article');
        card.className = 'item';

        card.setAttribute('data-realtime-booking-id', data.booking_id);
        card.style.cssText = 'display:block;margin-top:12px';

        var head = document.createElement('div');
        head.className = 'row sp';

        var invoice = document.createElement('strong');
        invoice.textContent =
            data.invoice_number || ('Booking #' + data.booking_id);

        var status = document.createElement('span');
        status.className = 'pill ' + (statusClasses[data.status || 'Menunggu'] || 'gray');
        status.setAttribute('data-realtime-status', '');
        status.textContent = data.status || 'Menunggu';

        head.appendChild(invoice);
        head.appendChild(status);
        card.appendChild(head);

        var pet = document.createElement('p');
        pet.className = 'sm';
        pet.style.cssText = 'margin:8px 0 2px';
        pet.textContent =
            (data.pet_name || 'Hewan') +
            (data.pet_species ? ' · ' + data.pet_species : '');

        card.appendChild(pet);

        var customer = document.createElement('p');
        customer.className = 'sm mut';
        customer.style.margin = '0';
        customer.textContent = data.customer_name || 'Pelanggan';

        card.appendChild(customer);

        var schedule = document.createElement('p');
        schedule.className = 'sm mut';
        schedule.style.margin = '4px 0';
        schedule.textContent = data.schedule_label || '';

        card.appendChild(schedule);

        var services = document.createElement('p');
        services.className = 'sm';
        services.style.margin = '4px 0';
        services.textContent = Array.isArray(data.service_names)
            ? data.service_names.join(', ')
            : '';

        card.appendChild(services);

        var actions = document.createElement('div');
        actions.className = 'row sp mt';

        var noteCount = document.createElement('span');
        noteCount.className = 'sm mut';
        noteCount.textContent = '0 catatan perawatan';

        var link = document.createElement('a');
        link.className = 'btn sm';
        link.href = data.url || '#';
        link.textContent = 'Kelola';

        actions.appendChild(noteCount);
        actions.appendChild(link);

        card.appendChild(actions);

        column.prepend(card);

        updateCareBoardStats();
    }

    // EVENT: CHAT MESSAGE SENT
    function appendMessage(data) {
        var list = document.querySelector('[data-realtime-messages]');

        if (
            !list ||
            !data ||
            !data.id ||
            list.querySelector('[data-message-id="' + data.id + '"]')
        ) {
            return;
        }

        var empty = list.querySelector('[data-empty-messages]');

        if (empty) empty.remove();

        var own = String(data.sender_id) === String(userId);

        var wrap = document.createElement('div');

        wrap.setAttribute('data-message-id', data.id);

        wrap.style.cssText =
            'display:flex;justify-content:' +
            (own ? 'flex-end' : 'flex-start') +
            ';margin-bottom:12px';

        var bubble = document.createElement('div');

        bubble.style.cssText =
            'max-width:75%;padding:12px 14px;border-radius:14px;' +
            'border:1px solid var(--line);background:' +
            (own ? 'rgba(224,182,74,.14)' : 'var(--field)');

        var header = document.createElement('div');
        header.className = 'row sp';
        header.style.cssText = 'gap:12px;margin-bottom:4px';

        var sender = document.createElement('strong');
        sender.className = 'sm';
        sender.textContent = own ? 'Anda' : (data.sender_name || 'Pengguna');

        var time = document.createElement('small');
        time.className = 'mut';
        time.textContent = data.created_at
            ? new Date(data.created_at).toLocaleString('id-ID')
            : 'Baru saja';

        var body = document.createElement('p');
        body.style.cssText = 'margin:0;white-space:pre-wrap';
        body.textContent = data.body || '';

        header.appendChild(sender);
        header.appendChild(time);

        bubble.appendChild(header);
        bubble.appendChild(body);

        wrap.appendChild(bubble);
        list.appendChild(wrap);

        list.scrollTop = list.scrollHeight;
    }

    // EVENT: CARE NOTE PUBLISHED
    function appendCareNote(data) {
        document.dispatchEvent(
            new CustomEvent('pawcare:care-note-published', {
                detail: data
            })
        );

        var target = document.querySelector('[data-realtime-care-notes]');

        if (!target || !data) return;

        var item = document.createElement('div');

        item.className = 'item';
        item.style.cssText =
            'flex-direction:column;align-items:stretch;gap:4px';

        var title = document.createElement('strong');

        title.textContent =
            'Catatan baru · ' + (data.author_name || 'Tim Bisnis');

        var summary = document.createElement('p');
        summary.style.margin = '4px 0';
        summary.textContent = data.care_summary || '';

        item.appendChild(title);
        item.appendChild(summary);

        if (data.recommendations) {
            var rec = document.createElement('small');

            rec.className = 'gold-t';
            rec.textContent =
                'Saran perawatan lanjutan: ' + data.recommendations;

            item.appendChild(rec);
        }

        target.prepend(item);
    }


    // EVENT: CATALOG CHANGED (REALTIME CATALOG & INVENTORY)
    function handleCatalogChanged(data) {
        document.dispatchEvent(
            new CustomEvent('pawcare:catalog-changed', {
                detail: data
            })
        );

        var banner = document.getElementById('catalogRealtimeBanner');
        var text = document.getElementById('catalogRealtimeText');
        if (banner) {
            if (text && data && data.service_name) {
                text.textContent = 'Layanan "' + data.service_name + '" baru saja diperbarui secara real-time.';
            }
            banner.style.display = 'flex';
        }
    }

    echo.channel('catalog.updates')
        .listen('.catalog.changed', handleCatalogChanged);

    // SUBSCRIBE PRIVATE CHANNELS

    if (userId) {
        echo.private('users.' + userId + '.tracking')
            .listen('.booking.status.updated', updateBooking)
            .listen('.care-note.published', appendCareNote);
    }

    if (businessId) {
        echo.private('business.' + businessId + '.care-board')
            .listen('.booking.created', appendBooking)
            .listen('.booking.status.updated', updateBooking)
            .listen('.care-note.published', appendCareNote);
    }

    if (conversationId) {
        echo.private('conversations.' + conversationId)
            .listen('.chat.message.sent', appendMessage);
    }

})();
