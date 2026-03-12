import type { WebuMapProps } from '../types';

/** Simple – data from CMS */
export function Map1({ embedUrl, address }: WebuMapProps) {
  return (
    <section className="webu-map webu-map--map-1">
      <div className="webu-map__inner">
        {embedUrl ? (
          <iframe
            src={embedUrl}
            title="Map"
            className="webu-map__iframe"
            width="100%"
            height="300"
            style={{ border: 0 }}
            allowFullScreen
            loading="lazy"
            referrerPolicy="no-referrer-when-downgrade"
          />
        ) : address ? (
          <p className="webu-map__address">{address}</p>
        ) : (
          <div className="webu-map__placeholder">Map (embedUrl or address from CMS)</div>
        )}
      </div>
    </section>
  );
}
