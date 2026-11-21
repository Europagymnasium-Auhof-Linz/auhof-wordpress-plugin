<?php
/**
 * Raumplan als PDF (A4 quer, alle Stockwerke im selben Maßstab) – ohne fremde Bibliothek.
 *
 * Die Datei wird am Server erzeugt, wenn der Raumplan gespeichert wird, nach einem Plugin-Update und wenn sie
 * fehlt (nur durch berechtigte Personen im Backend), und liegt fest unter uploads/auhof-raumplan/raumplan.pdf.
 * Besucher bekommen nur die fertige Datei.
 *
 * Aufbau und Zahlen entsprechen der Druckseite in assets/raumplan.js (stockRahmen, druckAnordnung, fitText).
 * Wer dort etwas ändert, muss es hier nachziehen; Referenz und Vergleichstest: tools/raumplan-pdf-prototyp.mjs.
 * Schrift: Helvetica/Helvetica-Bold (in jedem PDF-Programm eingebaut, gleiche Zeichenbreiten wie Arial).
 *
 * @package AuhofSchulseiten
 */

defined( 'ABSPATH' ) || exit;

const AUHOF_RAUMPLAN_PDF_ORDNER = 'auhof-raumplan';
const AUHOF_RAUMPLAN_PDF_DATEI  = 'raumplan.pdf';

/**
 * Zeichenbreiten (1/1000 der Schriftgröße) für die Zeichen 32–126.
 *
 * @param bool $fett Helvetica-Bold.
 * @return int[]
 */
function auhof_raumplan_pdf_breiten( $fett ) {
	static $normal = array( 278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584 );
	static $bold   = array( 278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584 );
	return $fett ? $bold : $normal;
}

/**
 * Text in einzelne Zeichen (UTF-8) zerlegen.
 *
 * @param string $text Text.
 * @return string[]
 */
function auhof_raumplan_pdf_zeichen( $text ) {
	$z = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
	return is_array( $z ) ? $z : array();
}

/**
 * Breite eines Textes bei Schriftgröße 1.
 *
 * @param string $text Text.
 * @param bool   $fett Fett.
 * @return float
 */
function auhof_raumplan_pdf_textbreite( $text, $fett ) {
	static $basis = array(
		'Ä' => 'A',
		'Ö' => 'O',
		'Ü' => 'U',
		'ä' => 'a',
		'ö' => 'o',
		'ü' => 'u',
		'é' => 'e',
		'è' => 'e',
		'á' => 'a',
		'à' => 'a',
		'ó' => 'o',
		'ú' => 'u',
		'í' => 'i',
		'ç' => 'c',
		'ñ' => 'n',
		'É' => 'E',
		'–' => '0',
		'·' => '.',
	);
	$breiten      = auhof_raumplan_pdf_breiten( $fett );
	$summe        = 0;
	foreach ( auhof_raumplan_pdf_zeichen( $text ) as $z ) {
		if ( 'ß' === $z ) {
			$summe += 611;
			continue;
		}
		if ( isset( $basis[ $z ] ) ) {
			$z = $basis[ $z ];
		}
		$c      = 1 === strlen( $z ) ? ord( $z ) : 0;
		$summe += ( $c < 32 || $c > 126 ) ? 556 : $breiten[ $c - 32 ];
	}
	return $summe / 1000;
}

/**
 * Zahl für den PDF-Inhalt: höchstens 3 Nachkommastellen, Punkt als Trennzeichen, unabhängig von der Sprache.
 *
 * @param float|int $x Zahl.
 * @return string
 */
function auhof_raumplan_pdf_zahl( $x ) {
	$s = number_format( round( (float) $x, 3 ), 3, '.', '' );
	$s = rtrim( rtrim( $s, '0' ), '.' );
	return ( '' === $s || '-0' === $s || '-' === $s ) ? '0' : $s;
}

/**
 * Text als PDF-Zeichenkette in WinAnsi (Windows-1252); Klammern und Rückstrich maskiert, Unbekanntes wird „?“.
 *
 * @param string $text Text (UTF-8).
 * @return string
 */
function auhof_raumplan_pdf_kette( $text ) {
	static $sonder = array(
		'–' => "\x96",
		'—' => "\x97",
		'„' => "\x84",
		'“' => "\x93",
		'€' => "\x80",
	);
	$aus           = '';
	foreach ( auhof_raumplan_pdf_zeichen( $text ) as $z ) {
		if ( isset( $sonder[ $z ] ) ) {
			$aus .= $sonder[ $z ];
			continue;
		}
		if ( 1 === strlen( $z ) ) {
			$c = ord( $z );
		} elseif ( 2 === strlen( $z ) ) {
			$c = ( ( ord( $z[0] ) & 0x1F ) << 6 ) | ( ord( $z[1] ) & 0x3F );
		} else {
			$c = 63;
		}
		if ( $c > 255 || $c < 32 || ( $c > 126 && $c < 160 ) ) {
			$c = 63;
		}
		$ch   = chr( $c );
		$aus .= ( '(' === $ch || ')' === $ch || '\\' === $ch ) ? '\\' . $ch : $ch;
	}
	return '(' . $aus . ')';
}

/**
 * Farbe „#rrggbb“ als drei PDF-Werte 0–1; alles andere wird Weiß.
 *
 * @param string $hex Farbe.
 * @return string
 */
function auhof_raumplan_pdf_farbe( $hex ) {
	if ( ! is_string( $hex ) || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $hex ) ) {
		$hex = '#ffffff';
	}
	$n = hexdec( substr( $hex, 1 ) );
	return auhof_raumplan_pdf_zahl( ( $n >> 16 ) / 255 ) . ' ' . auhof_raumplan_pdf_zahl( ( ( $n >> 8 ) & 255 ) / 255 ) . ' ' . auhof_raumplan_pdf_zahl( ( $n & 255 ) / 255 );
}

/**
 * Text an (x, y) auf der Grundlinie. Koordinaten mit y nach unten (wie im SVG), daher gespiegelte Textmatrix.
 *
 * @param string $text    Text.
 * @param float  $x       X.
 * @param float  $y       Y.
 * @param float  $groesse Schriftgröße.
 * @param bool   $fett    Fett.
 * @return string
 */
function auhof_raumplan_pdf_text( $text, $x, $y, $groesse, $fett ) {
	$z = 'auhof_raumplan_pdf_zahl';
	return 'BT /' . ( $fett ? 'F2' : 'F1' ) . ' ' . $z( $groesse ) . ' Tf 1 0 0 -1 ' . $z( $x ) . ' ' . $z( $y ) . ' Tm ' . auhof_raumplan_pdf_kette( $text ) . " Tj ET\n";
}

/**
 * Inhalt um 90° gegen den Uhrzeigersinn um (cx, cy) drehen (entspricht rotate(-90 cx cy) im SVG).
 *
 * @param string $inhalt Inhalt.
 * @param float  $cx     X.
 * @param float  $cy     Y.
 * @return string
 */
function auhof_raumplan_pdf_gedreht( $inhalt, $cx, $cy ) {
	return 'q 0 -1 1 0 ' . auhof_raumplan_pdf_zahl( $cx - $cy ) . ' ' . auhof_raumplan_pdf_zahl( $cy + $cx ) . " cm\n" . $inhalt . "Q\n";
}

/**
 * Beschriftung eines Raums; die Schriftgröße wird wie in raumplan.js (fitText) in das Kästchen eingepasst.
 *
 * @param array<string,mixed> $r Raum.
 * @return string
 */
function auhof_raumplan_pdf_raumtext( $r ) {
	$fett = ! empty( $r['bold'] );
	if ( ! empty( $r['spans'] ) && is_array( $r['spans'] ) ) {
		$aus = '';
		foreach ( $r['spans'] as $s ) {
			$t    = auhof_raumplan_pdf_text( (string) $s['t'], (float) $s['x'], (float) $s['y'], (float) $s['size'], ! empty( $s['bold'] ) );
			$aus .= ! empty( $s['rot'] ) ? auhof_raumplan_pdf_gedreht( $t, (float) $s['x'], (float) $s['y'] ) : $t;
		}
		return $aus;
	}
	list( $bx, $by, $bw, $bh ) = array_map( 'floatval', $r['box'] );
	$d                         = (string) $r['d'];
	$plain                     = str_replace( '|', '', $d );
	$rotate                    = ! empty( $r['rot'] ) || ( $bh > $bw * 1.8 ? count( auhof_raumplan_pdf_zeichen( $plain ) ) > 3 : false );
	$w                         = ( $rotate ? $bh : $bw ) - 2.4;
	$h                         = ( $rotate ? $bw : $bh ) - 1.6;
	$one                       = str_replace( '|', ' ', str_replace( '-|', '-', $d ) );
	$varianten                 = array( explode( '|', $d ), array( $one ) );
	if ( false !== strpos( $one, ' ' ) ) {
		$varianten[] = explode( ' ', $one );
	}
	$best = null;
	foreach ( $varianten as $zeilen ) {
		$max = 0.001;
		foreach ( $zeilen as $zeile ) {
			$max = max( $max, auhof_raumplan_pdf_textbreite( $zeile, $fett ) );
		}
		$groesse = min( (float) $r['size'] * 1.15, 9, $w / $max, $h / ( count( $zeilen ) * 1.12 ) );
		if ( null === $best || $groesse > $best['groesse'] + 0.01 ) {
			$best = array(
				'zeilen'  => $zeilen,
				'groesse' => $groesse,
			);
		}
	}
	$cx  = $bx + $bw / 2;
	$cy  = $by + $bh / 2;
	$lh  = $best['groesse'] * 1.12;
	$top = $cy - ( count( $best['zeilen'] ) - 1 ) * $lh / 2 + $best['groesse'] * 0.36;
	$aus = '';
	foreach ( array_values( $best['zeilen'] ) as $k => $zeile ) {
		$aus .= auhof_raumplan_pdf_text( $zeile, $cx - auhof_raumplan_pdf_textbreite( $zeile, $fett ) * $best['groesse'] / 2, $top + $k * $lh, $best['groesse'], $fett );
	}
	return $rotate ? auhof_raumplan_pdf_gedreht( $aus, $cx, $cy ) : $aus;
}

/**
 * Seitenaufteilung: eng zugeschnittener Ausschnitt je Stockwerk, Anordnung mit dem größten Maßstab, Plätze in mm.
 *
 * @param array<string,mixed> $p Plan.
 * @return array{rahmen:array<string,float[]>,mm:float,plaetze:array<string,float[]>,stock:string[]}
 */
function auhof_raumplan_pdf_aufteilung( $p ) {
	$stock  = array( '3', '2', '1', 'eg' );
	$breite = 277;
	$hoehe  = 188 - 9;
	$g      = 6;
	$rahmen = array();
	foreach ( $stock as $id ) {
		$v    = array_map( 'floatval', $p['views'][ $id ] );
		$x0   = INF;
		$y0   = INF;
		$x1   = -INF;
		$y1   = -INF;
		$dazu = function ( $x, $y, $w, $h ) use ( $v, &$x0, &$y0, &$x1, &$y1 ) {
			if ( $y + $h < $v[1] || $y > $v[1] + $v[3] || $x + $w < $v[0] || $x > $v[0] + $v[2] ) {
				return;
			}
			$x0 = min( $x0, $x );
			$y0 = min( $y0, $y );
			$x1 = max( $x1, $x + $w );
			$y1 = max( $y1, $y + $h );
		};
		foreach ( $p['fills'] as $f ) {
			$dazu( $f[0], $f[1], $f[2], $f[3] );
		}
		foreach ( $p['borders'] as $b ) {
			$dazu( $b[0], $b[1], $b[2], $b[3] );
		}
		foreach ( $p['rooms'] as $r ) {
			if ( (string) $r['f'] === $id ) {
				foreach ( $r['shape'] as $s ) {
					$dazu( $s[0], $s[1], $s[2], $s[3] );
				}
			}
		}
		foreach ( $p['static'] as $s ) {
			$dazu( $s['x'], $s['y'] - $s['size'], auhof_raumplan_pdf_textbreite( $s['t'], ! empty( $s['bold'] ) ) * $s['size'], $s['size'] * 1.25 );
		}
		if ( INF === $x0 ) {
			$rahmen[ $id ] = $v;
			continue;
		}
		$x0            = max( $v[0], $x0 - 2 );
		$y0            = max( $v[1], $y0 - 2 );
		$x1            = min( $v[0] + $v[2], $x1 + 2 );
		$y1            = min( $v[1] + $v[3], $y1 + 2 );
		$rahmen[ $id ] = array( $x0, $y0, $x1 - $x0, $y1 - $y0 );
	}
	$w      = function ( $id ) use ( $rahmen ) {
		return $rahmen[ $id ][2];
	};
	$h      = function ( $id ) use ( $rahmen ) {
		return $rahmen[ $id ][3];
	};
	$beste  = null;
	$anzahl = count( $stock );
	for ( $k = 1; $k < $anzahl; $k++ ) {
		$a          = array_slice( $stock, 0, $k );
		$b          = array_slice( $stock, $k );
		$kandidaten = array(
			array( 'spalten', min( ( $breite - $g ) / ( max( array_map( $w, $a ) ) + max( array_map( $w, $b ) ) ), ( $hoehe - $g * ( count( $a ) - 1 ) ) / array_sum( array_map( $h, $a ) ), ( $hoehe - $g * ( count( $b ) - 1 ) ) / array_sum( array_map( $h, $b ) ) ) ),
			array( 'zeilen', min( ( $hoehe - $g ) / ( max( array_map( $h, $a ) ) + max( array_map( $h, $b ) ) ), ( $breite - $g * ( count( $a ) - 1 ) ) / array_sum( array_map( $w, $a ) ), ( $breite - $g * ( count( $b ) - 1 ) ) / array_sum( array_map( $w, $b ) ) ) ),
		);
		foreach ( $kandidaten as $c ) {
			if ( null === $beste || $c[1] > $beste['mm'] + 1e-9 ) {
				$beste = array(
					'art'     => $c[0],
					'gruppen' => array( $a, $b ),
					'mm'      => $c[1],
				);
			}
		}
	}
	$mm      = $beste['mm'];
	$plaetze = array();
	if ( 'spalten' === $beste['art'] ) {
		$sb = array();
		foreach ( $beste['gruppen'] as $gr ) {
			$sb[] = max( array_map( $w, $gr ) ) * $mm;
		}
		$x = ( $breite - ( $sb[0] + $g + $sb[1] ) ) / 2;
		foreach ( $beste['gruppen'] as $k => $gr ) {
			$y = 9;
			foreach ( $gr as $id ) {
				$plaetze[ $id ] = array( $x, $y );
				$y             += $h( $id ) * $mm + $g;
			}
			$x += $sb[ $k ] + $g;
		}
	} else {
		$y = 9;
		foreach ( $beste['gruppen'] as $gr ) {
			$zb = array_sum( array_map( $w, $gr ) ) * $mm + $g * ( count( $gr ) - 1 );
			$x  = ( $breite - $zb ) / 2;
			foreach ( $gr as $id ) {
				$plaetze[ $id ] = array( $x, $y );
				$x             += $w( $id ) * $mm + $g;
			}
			$y += max( array_map( $h, $gr ) ) * $mm + $g;
		}
	}
	return array(
		'rahmen'  => $rahmen,
		'mm'      => $mm,
		'plaetze' => $plaetze,
		'stock'   => $stock,
	);
}

/**
 * PDF-Datei als Zeichenkette erzeugen.
 *
 * @param array<string,mixed> $p Plan aus auhof_raumplan_daten().
 * @return string Leer, wenn der Plan unvollständig ist.
 */
function auhof_raumplan_pdf_erzeugen( $p ) {
	foreach ( array( 'fills', 'borders', 'polys', 'rooms', 'static', 'views' ) as $teil ) {
		if ( ! isset( $p[ $teil ] ) || ! is_array( $p[ $teil ] ) ) {
			return '';
		}
	}
	$z     = 'auhof_raumplan_pdf_zahl';
	$jahr  = isset( $p['year'] ) ? (string) $p['year'] : '';
	$a     = auhof_raumplan_pdf_aufteilung( $p );
	$pt    = 72 / 25.4;
	$titel = 'Raumplan · ' . $jahr;
	// Seite: Punkte, Ursprung unten links → mm, Ursprung oben links, y nach unten; Rand (297−277)/2 bzw. (210−188)/2.
	$s  = $z( $pt ) . ' 0 0 ' . $z( -$pt ) . ' 0 ' . $z( 210 * $pt ) . " cm\n1 0 0 1 " . $z( ( 297 - 277 ) / 2 ) . ' ' . $z( ( 210 - 188 ) / 2 ) . " cm\n0 g\n";
	$s .= auhof_raumplan_pdf_text( $titel, 0, 4.5, 4.2, true );
	foreach ( $a['stock'] as $id ) {
		$r   = $a['rahmen'][ $id ];
		$m   = $a['mm'];
		$pos = $a['plaetze'][ $id ];
		$s  .= "q\n" . $z( $m ) . ' 0 0 ' . $z( $m ) . ' ' . $z( $pos[0] - $r[0] * $m ) . ' ' . $z( $pos[1] - $r[1] * $m ) . " cm\n" . implode( ' ', array_map( $z, $r ) ) . " re W n\n";
		foreach ( $p['fills'] as $f ) {
			$s .= auhof_raumplan_pdf_farbe( isset( $f[4] ) ? $f[4] : '' ) . ' rg ' . $z( $f[0] ) . ' ' . $z( $f[1] ) . ' ' . $z( $f[2] ) . ' ' . $z( $f[3] ) . " re f\n";
		}
		$s .= "0 g\n";
		foreach ( $p['borders'] as $b ) {
			$s .= $z( $b[0] ) . ' ' . $z( $b[1] ) . ' ' . $z( $b[2] ) . ' ' . $z( $b[3] ) . " re\n";
		}
		foreach ( $p['polys'] as $poly ) {
			$teile = array();
			foreach ( array_values( $poly ) as $i => $q ) {
				$teile[] = $z( $q[0] ) . ' ' . $z( $q[1] ) . ' ' . ( $i ? 'l' : 'm' );
			}
			$s .= implode( ' ', $teile ) . " h\n";
		}
		$s .= "f\n";
		foreach ( $p['rooms'] as $raum ) {
			if ( (string) $raum['f'] === $id ) {
				$s .= auhof_raumplan_pdf_raumtext( $raum );
			}
		}
		foreach ( $p['static'] as $t ) {
			// Nur Beschriftungen dieses Stockwerks (Grundlinie im Ausschnitt).
			if ( $t['y'] >= $r[1] && $t['y'] <= $r[1] + $r[3] && $t['x'] >= $r[0] && $t['x'] <= $r[0] + $r[2] ) {
				$s .= auhof_raumplan_pdf_text( (string) $t['t'], (float) $t['x'], (float) $t['y'], (float) $t['size'], ! empty( $t['bold'] ) );
			}
		}
		$s .= "Q\n";
	}
	$objekte = array(
		'<< /Type /Catalog /Pages 2 0 R /Lang (de-AT) >>',
		'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
		'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $z( 297 * $pt ) . ' ' . $z( 210 * $pt ) . '] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>',
		'<< /Length ' . strlen( $s ) . " >>\nstream\n" . $s . 'endstream',
		'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
		'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
		'<< /Title ' . auhof_raumplan_pdf_kette( $titel ) . ' /Producer (Auhof Schulseiten) >>',
	);
	$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
	$pos     = array();
	foreach ( $objekte as $i => $o ) {
		$pos[] = strlen( $pdf );
		$pdf  .= ( $i + 1 ) . " 0 obj\n" . $o . "\nendobj\n";
	}
	$xref = strlen( $pdf );
	$pdf .= "xref\n0 " . ( count( $objekte ) + 1 ) . "\n0000000000 65535 f \n";
	foreach ( $pos as $n ) {
		$pdf .= sprintf( '%010d', $n ) . " 00000 n \n";
	}
	$pdf .= "trailer\n<< /Size " . ( count( $objekte ) + 1 ) . " /Root 1 0 R /Info 7 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
	return $pdf;
}

/**
 * Ort der fertigen Datei.
 *
 * @return array{pfad:string,url:string,ordner:string}
 */
function auhof_raumplan_pdf_ort() {
	$up = wp_get_upload_dir();
	return array(
		'ordner' => $up['basedir'] . '/' . AUHOF_RAUMPLAN_PDF_ORDNER,
		'pfad'   => $up['basedir'] . '/' . AUHOF_RAUMPLAN_PDF_ORDNER . '/' . AUHOF_RAUMPLAN_PDF_DATEI,
		'url'    => $up['baseurl'] . '/' . AUHOF_RAUMPLAN_PDF_ORDNER . '/' . AUHOF_RAUMPLAN_PDF_DATEI,
	);
}

/**
 * Datei neu schreiben (erst in eine Zwischendatei, dann umbenennen) und den Stand merken.
 *
 * @return bool
 */
function auhof_raumplan_pdf_schreiben() {
	$daten = auhof_raumplan_daten();
	$pdf   = $daten ? auhof_raumplan_pdf_erzeugen( $daten ) : '';
	if ( '' === $pdf ) {
		return false;
	}
	$ort = auhof_raumplan_pdf_ort();
	if ( ! wp_mkdir_p( $ort['ordner'] ) ) {
		return false;
	}
	$tmp = $ort['pfad'] . '.tmp';
	if ( false === file_put_contents( $tmp, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- feste Datei im Upload-Ordner.
		return false;
	}
	if ( ! @rename( $tmp, $ort['pfad'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
		wp_delete_file( $tmp );
		return false;
	}
	update_option( 'auhof_raumplan_pdf_stand', AUHOF_SCHULSEITEN_VERSION . '|' . md5( $pdf ), false );
	return true;
}

// Nach dem Speichern des Raumplans (Einstellungs-API; Recht und Nonce prüft WordPress in options.php).
add_action( 'update_option_auhof_raumplan', 'auhof_raumplan_pdf_schreiben', 20, 0 );
add_action( 'add_option_auhof_raumplan', 'auhof_raumplan_pdf_schreiben', 20, 0 );

add_action( 'admin_init', 'auhof_raumplan_pdf_pruefen', 30 );

/**
 * Nach einem Plugin-Update oder wenn die Datei fehlt: neu erzeugen – nur im Backend durch berechtigte Personen.
 */
function auhof_raumplan_pdf_pruefen() {
	if ( ! current_user_can( auhof_schulseiten_recht() ) ) {
		return;
	}
	$stand = (string) get_option( 'auhof_raumplan_pdf_stand', '' );
	if ( 0 === strpos( $stand, AUHOF_SCHULSEITEN_VERSION . '|' ) && is_file( auhof_raumplan_pdf_ort()['pfad'] ) ) {
		return;
	}
	auhof_raumplan_pdf_schreiben();
}

/**
 * Adresse der fertigen Datei mit Versionsanhang (damit Browser eine neue Fassung laden); leer, wenn sie fehlt.
 *
 * @return string
 */
function auhof_raumplan_pdf_url() {
	$ort   = auhof_raumplan_pdf_ort();
	$stand = (string) get_option( 'auhof_raumplan_pdf_stand', '' );
	if ( '' === $stand || ! is_file( $ort['pfad'] ) ) {
		return '';
	}
	$teile = explode( '|', $stand );
	return add_query_arg( 'v', substr( (string) end( $teile ), 0, 10 ), $ort['url'] );
}
