/**
 * شاشة «ميداني»: فورم الزيارة (مسؤولون متعددون + المحافظة→المنطقة + خريطة Leaflet
 * مع تحديد الموقع الحالي من الموبايل) وخريطة القراءة فقط في صفحة الزيارة.
 *
 * Leaflet يُحمَّل من CDN في الصفحة نفسها (@push('scripts'))؛ لو لم يصل (بلا شبكة)
 * تبقى حقول الإحداثيات تعمل يدويًا.
 */
import { withAlpine } from './alpine';
import { rowRepeater } from './repeater';

const KUWAIT = { lat: 29.3375, lng: 48.0758 };
const BLANK_CONTACT = { id: '', name: '', role: '', phone_code: '+965', phone: '' };
const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const TILE_OPTIONS = {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
};
const GEO_ERRORS = {
    1: 'تم رفض إذن الوصول إلى الموقع — فعّله من إعدادات المتصفح ثم حاول مجددًا.',
    2: 'تعذّر تحديد الموقع الحالي.',
    3: 'انتهت مهلة تحديد الموقع، حاول مرة أخرى.',
};

/** ينفّذ الدالة عندما يكون Leaflet جاهزًا (فورًا أو بعد اكتمال تحميل الصفحة) */
function whenLeaflet(callback) {
    if (typeof window.L !== 'undefined') {
        callback(window.L);
        return;
    }

    window.addEventListener('load', () => {
        if (typeof window.L !== 'undefined') {
            callback(window.L);
        }
    }, { once: true });
}

function parse(value) {
    const number = Number.parseFloat(value);

    return Number.isFinite(number) ? number : null;
}

withAlpine((Alpine) => {
    Alpine.data('fieldOwnerForm', (opts = {}) => {
        // كائنات Leaflet تبقى خارج حالة Alpine التفاعلية (لا تُلفّ في Proxy)
        let L = null;
        let map = null;
        let marker = null;

        return {
            ...rowRepeater({ prefix: 'contacts', blank: BLANK_CONTACT, rows: opts.rows ?? [], errors: opts.errors ?? {} }),

            countries: opts.countries ?? [],
            city: String(opts.city ?? ''),
            area: String(opts.area ?? ''),
            areas: opts.areas ?? [],          // [{ id, name, city_id }]
            lat: String(opts.lat ?? ''),
            lng: String(opts.lng ?? ''),
            geoBusy: false,
            geoError: '',

            init() {
                if (! this.rows.length) {
                    this.add();
                }

                // منطقة محفوظة بدون محافظة → نستنتج المحافظة
                if (this.area && ! this.city) {
                    this.onAreaChange();
                }

                this.$nextTick(() => whenLeaflet((leaflet) => this.initMap(leaflet)));
            },

            // ===== المحافظة → المنطقة =====

            areasFor() {
                return this.city ? this.areas.filter((a) => String(a.city_id) === this.city) : this.areas;
            },

            onCityChange() {
                if (this.area && ! this.areasFor().some((a) => String(a.id) === this.area)) {
                    this.area = '';
                }
            },

            onAreaChange() {
                const area = this.areas.find((a) => String(a.id) === this.area);
                if (area && area.city_id && ! this.city) {
                    this.city = String(area.city_id);
                }
            },

            /** السطر الأخير لا يُحذف — مسؤول واحد على الأقل */
            removeContact(index) {
                if (this.rows.length > 1) {
                    this.remove(index);
                }
            },

            // ===== الخريطة =====

            initMap(leaflet) {
                const container = this.$refs.map;
                if (! container || map) {
                    return;
                }

                L = leaflet;
                const lat = parse(this.lat);
                const lng = parse(this.lng);
                const has = lat !== null && lng !== null;

                map = L.map(container).setView(has ? [lat, lng] : [KUWAIT.lat, KUWAIT.lng], has ? 16 : 11);
                L.tileLayer(TILES, TILE_OPTIONS).addTo(map);

                if (has) {
                    this.placeMarker(lat, lng);
                }

                map.on('click', (event) => this.setLocation(event.latlng.lat, event.latlng.lng));
                setTimeout(() => map.invalidateSize(), 200);
                window.addEventListener('resize', () => map && map.invalidateSize());
            },

            placeMarker(lat, lng) {
                if (! map) {
                    return;
                }

                if (marker) {
                    marker.setLatLng([lat, lng]);
                    return;
                }

                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', (event) => {
                    const point = event.target.getLatLng();
                    this.setLocation(point.lat, point.lng, false);
                });
            },

            setLocation(lat, lng, moveMarker = true) {
                this.lat = Number(lat).toFixed(7);
                this.lng = Number(lng).toFixed(7);

                if (moveMarker) {
                    this.placeMarker(Number(lat), Number(lng));
                }
            },

            /** كتابة الإحداثيات يدويًا تحرّك الدبوس */
            moveFromInputs() {
                const lat = parse(this.lat);
                const lng = parse(this.lng);

                if (! map || lat === null || lng === null) {
                    return;
                }

                this.placeMarker(lat, lng);
                map.panTo([lat, lng]);
            },

            clearLocation() {
                this.lat = '';
                this.lng = '';

                if (marker) {
                    marker.remove();
                    marker = null;
                }
            },

            /** موقعي الحالي (الموبايل) — يحتاج HTTPS أو localhost */
            locateMe() {
                this.geoError = '';

                if (! navigator.geolocation) {
                    this.fail('المتصفح لا يدعم تحديد الموقع.');
                    return;
                }

                this.geoBusy = true;

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.geoBusy = false;
                        const { latitude, longitude } = position.coords;
                        this.setLocation(latitude, longitude);

                        if (map) {
                            map.setView([latitude, longitude], 17);
                        }
                    },
                    (error) => {
                        this.geoBusy = false;
                        this.fail(GEO_ERRORS[error.code] ?? GEO_ERRORS[2]);
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
                );
            },

            fail(message) {
                this.geoError = message;
                setTimeout(() => { this.geoError = ''; }, 5000);
            },
        };
    });

    /** خريطة للقراءة فقط بدبوس ثابت (صفحة الزيارة) */
    Alpine.data('fieldOwnerMap', (opts = {}) => ({
        init() {
            const lat = parse(opts.lat);
            const lng = parse(opts.lng);

            if (lat === null || lng === null) {
                return;
            }

            this.$nextTick(() => whenLeaflet((L) => {
                const map = L.map(this.$el, { scrollWheelZoom: false }).setView([lat, lng], 16);
                L.tileLayer(TILES, TILE_OPTIONS).addTo(map);
                L.marker([lat, lng]).addTo(map);
                setTimeout(() => map.invalidateSize(), 200);
            }));
        },
    }));
});
