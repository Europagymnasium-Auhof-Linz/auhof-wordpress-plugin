<?php
/**
 * Diashow der Startseite: Einstellungen (Bilder, Dauer, Breite, Höhe), Ausgabe [auhof_diashow] und
 * optimierte Bildfassungen (genau zugeschnittene WebP-Dateien für Computer und Handy).
 *
 * Barrierefreiheit (WCAG 2.2.2, 2.1.1, 2.3.3): sichtbarer Pause-Knopf, Anhalten bei Maus oder
 * Tastaturfokus, kein automatisches Wechseln bei „Bewegung reduzieren“, Bedienung per Tastatur,
 * nicht sichtbare Bilder sind für Tastatur und Screenreader ausgeblendet.
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

/** Unterordner in „uploads“ für die optimierten Fassungen. */
const AUHOF_DIASHOW_ORDNER = 'auhof-diashow';

/** Bis zu dieser Fensterbreite gilt die Handy-Ansicht (Seitenverhältnis statt fester Höhe). */
const AUHOF_DIASHOW_HANDY_BIS = 600;

/**
 * Standardwerte.
 *
 * @return array<string,mixed>
 */
function auhof_diashow_standard() {
	return array(
		'bilder'       => array(),
		'sekunden'     => 6,
		'breite'       => 'voll',
		'hoehe'        => 400,
		'handy'        => '3:2',
		'beschriftung' => false,
	);
}

/**
 * Bildausschnitt je Bild: senkrechte Lage des Ausschnitts (0 = oben, 1 = unten).
 *
 * @return array<string,array{0:string,1:float}>
 */
function auhof_diashow_ausschnitte() {
	return array(
		'oben'  => array( __( 'oben', 'auhof-schulseiten' ), 0.15 ),
		'mitte' => array( __( 'Mitte', 'auhof-schulseiten' ), 0.4 ),
		'unten' => array( __( 'unten', 'auhof-schulseiten' ), 0.75 ),
	);
}

/**
 * Seitenverhältnisse am Handy.
 *
 * @return array<string,float>
 */
function auhof_diashow_handy_formate() {
	return array(
		'3:2'  => 3 / 2,
		'4:3'  => 4 / 3,
		'16:9' => 16 / 9,
		'1:1'  => 1.0,
	);
}

/**
 * Gespeicherte Einstellungen, vervollständigt mit Standardwerten.
 *
 * @return array<string,mixed>
 */
function auhof_diashow_einstellungen() {
	$wert = get_option( 'auhof_diashow', array() );
	return array_merge( auhof_diashow_standard(), is_array( $wert ) ? $wert : array() );
}

/**
 * Benötigte Fassungen: Computer („d“, Seitenverhältnis 1440 : Höhe) und Handy („m“).
 *
 * @param array<string,mixed> $e Einstellungen.
 * @return array<int,array{art:string,w:int,h:int}>
 */
function auhof_diashow_fassungen( $e ) {
	$formate = auhof_diashow_handy_formate();
	$handy   = isset( $formate[ $e['handy'] ] ) ? $formate[ $e['handy'] ] : 1.5;
	$liste   = array();
	foreach ( array( 1024, 1440, 1920, 2560 ) as $w ) {
		$liste[] = array(
			'art' => 'd',
			'w'   => $w,
			'h'   => (int) round( $w * (int) $e['hoehe'] / 1440 ),
		);
	}
	foreach ( array( 480, 780, 1170 ) as $w ) {
		$liste[] = array(
			'art' => 'm',
			'w'   => $w,
			'h'   => (int) round( $w / $handy ),
		);
	}
	return $liste;
}

/**
 * Pfad und Adresse einer Fassung.
 *
 * @param int                 $id      Bild-ID.
 * @param array<string,mixed> $fassung Fassung.
 * @param string              $lage    Ausschnitt.
 * @return array{pfad:string,url:string}
 */
function auhof_diashow_datei( $id, $fassung, $lage ) {
	$up   = wp_get_upload_dir();
	$name = sprintf( '%d-%s%dx%d-%s.webp', $id, $fassung['art'], $fassung['w'], $fassung['h'], $lage );
	return array(
		'pfad' => $up['basedir'] . '/' . AUHOF_DIASHOW_ORDNER . '/' . $name,
		'url'  => $up['baseurl'] . '/' . AUHOF_DIASHOW_ORDNER . '/' . $name,
	);
}

/**
 * Erzeugt fehlende Fassungen (zugeschnitten, WebP, Qualität 78). Bricht nach $zeit Sekunden ab und plant
 * den Rest im Hintergrund (WP-Cron). Vergrößert nie über das Original hinaus.
 *
 * @param int $zeit Zeitbudget in Sekunden.
 * @return array{fertig:int,gesamt:int}
 */
function auhof_diashow_erzeugen( $zeit = 20 ) {
	$e      = auhof_diashow_einstellungen();
	$start  = microtime( true );
	$fertig = 0;
	$gesamt = 0;
	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return compact( 'fertig', 'gesamt' );
	}
	wp_mkdir_p( wp_get_upload_dir()['basedir'] . '/' . AUHOF_DIASHOW_ORDNER );
	$lagen = auhof_diashow_ausschnitte();
	foreach ( $e['bilder'] as $bild ) {
		$quelle = get_attached_file( (int) $bild['id'] );
		$masse  = $quelle && is_file( $quelle ) ? wp_getimagesize( $quelle ) : false;
		if ( ! $masse ) {
			continue;
		}
		list( $sw, $sh ) = $masse;
		foreach ( auhof_diashow_fassungen( $e ) as $f ) {
			// Ausschnitt mit dem Seitenverhältnis der Fassung aus dem Original.
			$a = $f['w'] / $f['h'];
			if ( $sw / $sh > $a ) {
				$cw = $sh * $a;
				$ch = $sh;
			} else {
				$cw = $sw;
				$ch = $sw / $a;
			}
			if ( $cw < $f['w'] ) {
				continue; // Original zu klein für diese Breite: nicht vergrößern.
			}
			++$gesamt;
			$datei = auhof_diashow_datei( (int) $bild['id'], $f, $bild['lage'] );
			if ( is_file( $datei['pfad'] ) ) {
				++$fertig;
				continue;
			}
			if ( microtime( true ) - $start > $zeit ) {
				continue;
			}
			$ed = wp_get_image_editor( $quelle );
			if ( is_wp_error( $ed ) ) {
				continue;
			}
			$x = ( $sw - $cw ) / 2;
			$y = ( $sh - $ch ) * $lagen[ $bild['lage'] ][1];
			$ed->set_quality( 78 );
			if ( ! is_wp_error( $ed->crop( (int) round( $x ), (int) round( $y ), (int) round( $cw ), (int) round( $ch ), $f['w'], $f['h'] ) )
				&& ! is_wp_error( $ed->save( $datei['pfad'], 'image/webp' ) ) ) {
				++$fertig;
			}
		}
	}
	if ( $fertig < $gesamt && ! wp_next_scheduled( 'auhof_diashow_erzeugen' ) ) {
		wp_schedule_single_event( time() + 30, 'auhof_diashow_erzeugen' );
	}
	update_option(
		'auhof_diashow_stand',
		array(
			'fertig' => $fertig,
			'gesamt' => $gesamt,
		),
		false
	);
	return compact( 'fertig', 'gesamt' );
}

add_action(
	'auhof_diashow_erzeugen',
	function () {
		auhof_diashow_erzeugen( 25 );
	}
);

/**
 * Entfernt Fassungen, die zu keiner aktuellen Einstellung mehr gehören.
 */
function auhof_diashow_aufraeumen() {
	$e       = auhof_diashow_einstellungen();
	$gueltig = array();
	foreach ( $e['bilder'] as $bild ) {
		foreach ( auhof_diashow_fassungen( $e ) as $f ) {
			$gueltig[ basename( auhof_diashow_datei( (int) $bild['id'], $f, $bild['lage'] )['pfad'] ) ] = true;
		}
	}
	foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . AUHOF_DIASHOW_ORDNER . '/*.webp' ) as $datei ) {
		if ( is_string( $datei ) && ! isset( $gueltig[ basename( $datei ) ] ) ) {
			wp_delete_file( $datei );
		}
	}
}

/**
 * Srcset aus vorhandenen Fassungen einer Art („d“ oder „m“).
 *
 * @param int                 $id   Bild-ID.
 * @param string              $lage Ausschnitt.
 * @param string              $art  „d“ oder „m“.
 * @param array<string,mixed> $e    Einstellungen.
 * @return string
 */
function auhof_diashow_srcset( $id, $lage, $art, $e ) {
	$teile = array();
	foreach ( auhof_diashow_fassungen( $e ) as $f ) {
		if ( $art !== $f['art'] ) {
			continue;
		}
		$datei = auhof_diashow_datei( $id, $f, $lage );
		if ( is_file( $datei['pfad'] ) ) {
			$teile[] = esc_url( $datei['url'] ) . ' ' . $f['w'] . 'w';
		}
	}
	return implode( ', ', $teile );
}

add_shortcode( 'auhof_diashow', 'auhof_diashow_shortcode' );

/**
 * Shortcode [auhof_diashow] – zeigt die im Backend (Diashow) eingestellten Bilder.
 * Ältere Form [auhof_diashow ids="1,2,3" sekunden="6" beschriftung="nein"] funktioniert weiterhin.
 *
 * @param array<string,string>|string $atts Attribute.
 * @return string
 */
function auhof_diashow_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'ids'          => '',
			'sekunden'     => '',
			'beschriftung' => '',
			'titel'        => __( 'Eindrücke aus dem Schulleben', 'auhof-schulseiten' ),
		),
		$atts,
		'auhof_diashow'
	);
	$e    = auhof_diashow_einstellungen();
	if ( '' !== $atts['ids'] ) {
		$e['bilder'] = array();
		foreach ( array_filter( array_map( 'absint', explode( ',', (string) $atts['ids'] ) ) ) as $id ) {
			$e['bilder'][] = array(
				'id'   => $id,
				'lage' => 'mitte',
			);
		}
	}
	if ( '' !== $atts['sekunden'] ) {
		$e['sekunden'] = (int) $atts['sekunden'];
	}
	if ( '' !== $atts['beschriftung'] ) {
		$e['beschriftung'] = ! in_array( strtolower( (string) $atts['beschriftung'] ), array( 'nein', '0', 'false', 'aus' ), true );
	}
	$bilder = array_values(
		array_filter(
			$e['bilder'],
			function ( $b ) {
				return wp_attachment_is_image( (int) $b['id'] );
			}
		)
	);
	if ( ! $bilder ) {
		return '';
	}

	$anzahl = count( $bilder );
	$lagen  = auhof_diashow_ausschnitte();
	$inhalt = 'inhalt' === $e['breite'];
	$sizes  = $inhalt ? '(min-width: 1200px) 1200px, 100vw' : '100vw';
	$folien = '';
	foreach ( $bilder as $i => $bild ) {
		$id      = (int) $bild['id'];
		$lage    = isset( $lagen[ $bild['lage'] ] ) ? $bild['lage'] : 'mitte';
		$img     = wp_get_attachment_image(
			$id,
			'full',
			false,
			array(
				'class'         => 'auhof-diashow__bild',
				'sizes'         => $sizes,
				'loading'       => 0 === $i ? 'eager' : 'lazy',
				'fetchpriority' => 0 === $i ? 'high' : 'low',
				'decoding'      => 'async',
				'style'         => 'object-position: center ' . ( $lagen[ $lage ][1] * 100 ) . '%',
			)
		);
		$quellen = '';
		$m       = auhof_diashow_srcset( $id, $lage, 'm', $e );
		$d       = auhof_diashow_srcset( $id, $lage, 'd', $e );
		if ( $m ) {
			$quellen .= '<source type="image/webp" media="(max-width: ' . AUHOF_DIASHOW_HANDY_BIS . 'px)" srcset="' . $m . '" sizes="100vw">';
		}
		if ( $d ) {
			$quellen .= '<source type="image/webp" media="(min-width: ' . ( AUHOF_DIASHOW_HANDY_BIS + 1 ) . 'px)" srcset="' . $d . '" sizes="' . esc_attr( $sizes ) . '">';
		}
		$bildcode = '<picture>' . $quellen . $img . '</picture>';
		// Nur das erste Bild sofort laden; die übrigen lädt das Skript kurz bevor sie erscheinen.
		if ( $i > 0 ) {
			$bildcode = preg_replace( '/\s(src|srcset)=/', ' data-$1=', $bildcode );
			$bildcode = str_replace( '<img ', '<img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" ', $bildcode );
		}

		$unterschrift = '';
		if ( $e['beschriftung'] ) {
			$anhang = get_post( $id );
			$text   = $anhang ? trim( (string) $anhang->post_excerpt ) : '';
			$eltern = ( $anhang && $anhang->post_parent ) ? get_post( $anhang->post_parent ) : null;
			$link   = ( $eltern && 'publish' === $eltern->post_status ) ? get_permalink( $eltern ) : '';
			if ( '' === $text && $eltern ) {
				$text = get_the_title( $eltern );
			}
			if ( '' !== $text ) {
				$unterschrift = '<figcaption class="auhof-diashow__text">'
					. ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>' : esc_html( $text ) )
					. '</figcaption>';
			}
		}
		$folien .= sprintf(
			'<div class="auhof-diashow__folie%s" role="group" aria-roledescription="%s" aria-label="%s"%s><figure>%s%s</figure></div>',
			0 === $i ? ' ist-aktiv' : '',
			esc_attr__( 'Folie', 'auhof-schulseiten' ),
			esc_attr( sprintf( /* translators: 1: Nummer, 2: Anzahl */ __( '%1$d von %2$d', 'auhof-schulseiten' ), $i + 1, $anzahl ) ),
			0 === $i ? '' : ' aria-hidden="true" inert',
			$bildcode,
			$unterschrift
		);
	}

	$steuerung = '';
	if ( $anzahl > 1 ) {
		$steuerung = '<div class="auhof-diashow__steuerung">'
			. '<button type="button" class="auhof-diashow__knopf auhof-diashow__pause" aria-pressed="false"><span class="auhof-diashow__symbol" aria-hidden="true"></span><span class="auhof-diashow__label">' . esc_html__( 'Pause', 'auhof-schulseiten' ) . '</span></button>'
			. '<button type="button" class="auhof-diashow__knopf auhof-diashow__zurueck"><span aria-hidden="true">‹</span><span class="screen-reader-text">' . esc_html__( 'Vorheriges Bild', 'auhof-schulseiten' ) . '</span></button>'
			. '<span class="auhof-diashow__zaehler" aria-hidden="true">1 / ' . $anzahl . '</span>'
			. '<button type="button" class="auhof-diashow__knopf auhof-diashow__weiter"><span aria-hidden="true">›</span><span class="screen-reader-text">' . esc_html__( 'Nächstes Bild', 'auhof-schulseiten' ) . '</span></button>'
			. '</div>';
	}

	$formate = auhof_diashow_handy_formate();
	$handy   = isset( $formate[ $e['handy'] ] ) ? $e['handy'] : '3:2';
	$hoehe   = (int) $e['hoehe'];
	$stil    = sprintf(
		'--auhof-dia-hoehe:%dpx;--auhof-dia-min:%dpx;--auhof-dia-vw:%svw;--auhof-dia-handy:%s',
		$hoehe,
		(int) round( $hoehe * 0.6 ),
		number_format( $hoehe / 14.4, 2, '.', '' ),
		str_replace( ':', ' / ', $handy )
	);

	wp_enqueue_style( 'auhof-diashow' );
	wp_enqueue_script( 'auhof-diashow' );

	return sprintf(
		'<section class="auhof-diashow%s" aria-roledescription="%s" aria-label="%s" data-sekunden="%d" style="%s">%s<div class="auhof-diashow__folien" aria-live="off">%s</div></section>',
		$inhalt ? ' auhof-diashow--inhalt' : '',
		esc_attr__( 'Karussell', 'auhof-schulseiten' ),
		esc_attr( $atts['titel'] ),
		max( 3, min( 30, (int) $e['sekunden'] ) ),
		esc_attr( $stil ),
		$steuerung,
		$folien
	);
}

add_action( 'wp_enqueue_scripts', 'auhof_diashow_assets' );

/**
 * Stylesheet und Skript registrieren; das Stylesheet bei Bedarf im Kopf laden (kein Flackern).
 */
function auhof_diashow_assets() {
	wp_register_style( 'auhof-diashow', AUHOF_SCHULSEITEN_URL . 'assets/diashow.css', array(), AUHOF_SCHULSEITEN_VERSION );
	wp_register_script(
		'auhof-diashow',
		AUHOF_SCHULSEITEN_URL . 'assets/diashow.js',
		array(),
		AUHOF_SCHULSEITEN_VERSION,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
	$post = get_post();
	if ( is_singular() && $post && has_shortcode( $post->post_content, 'auhof_diashow' ) ) {
		wp_enqueue_style( 'auhof-diashow' );
	}
}
