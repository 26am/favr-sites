// The footer credit's script in real Chrome, against footer shapes met on Favr sites.
// Run: node tests/browser/credit.mjs   (needs Google Chrome; prints "ok"/"FAIL" per case).
import { spawn } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const css = readFileSync( join( root, 'assets/credit/credit.css' ), 'utf8' );
const js = readFileSync( join( root, 'assets/credit/credit.js' ), 'utf8' );
const CREDIT = '<div class="favr-credit" data-favr-credit><p class="favr-credit__text">Website powered by <a href="https://favr.site/">Favr</a></p></div>';

// Elementor-shaped footers: the background is on the containers, never on the <footer> wrapper.
const row = ( bg, inner ) => `<div class="e-con e-parent" style="background:${ bg };padding:20px"><div class="e-con-inner">${ inner }</div></div>`;
const footer = ( rows ) => `<footer data-elementor-type="footer" class="elementor elementor-55 elementor-location-footer">${ rows }</footer>`;
const NAVY = '#1b2a4a';
const cases = [
	{ name: 'a dark bottom row', body: footer( row( '#000', '<p>About</p>' ) + row( NAVY, '<p>© 2026</p>' ) ), bg: [ 27, 42, 74 ] },
	{ name: 'a last row hidden at this width', body: footer( row( NAVY, '<p>© 2026</p>' ) + `<div class="e-con" style="display:none;background:#fff">Call us</div>` ), bg: [ 27, 42, 74 ] },
	{ name: 'a fixed back-to-top button last', body: footer( row( NAVY, '<p>© 2026</p>' ) + `<div class="e-con" style="position:fixed;right:10px;bottom:10px;width:44px;height:44px;background:#f97316"></div>` ), bg: [ 27, 42, 74 ] },
	{ name: 'a full-width button ending the last row', body: footer( row( NAVY, '<div class="elementor-widget elementor-widget-button"><div class="elementor-widget-container"><a class="elementor-button" style="display:block;background:#f97316;color:#fff">Join</a></div></div>' ) ), bg: [ 27, 42, 74 ] },
	{ name: 'a mid-tone blue bottom row', body: footer( row( '#2563eb', '<p>© 2026</p>' ) ), bg: [ 37, 99, 235 ] },
	{ name: 'a mid-grey bottom row (the hardest for white or black text)', body: footer( row( '#757575', '<p>© 2026</p>' ) ), bg: [ 117, 117, 117 ] },
	{ name: 'a footer with no background on a white page', body: footer( '<div class="e-con"><p>© 2026</p></div>' ), bg: null },
];

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const dir = mkdtempSync( join( tmpdir(), 'favr-credit-' ) );
const chrome = spawn( CHROME, [ '--headless=new', '--disable-gpu', '--no-first-run', `--user-data-dir=${ dir }`, '--remote-debugging-port=0', 'about:blank' ], { stdio: 'ignore' } );
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );
let port = 0;
for ( let i = 0; i < 100 && ! port; i++ ) {
	await sleep( 100 );
	const f = join( dir, 'DevToolsActivePort' );
	if ( existsSync( f ) ) {
		port = parseInt( readFileSync( f, 'utf8' ), 10 );
	}
}
const page = ( await ( await fetch( `http://127.0.0.1:${ port }/json/list` ) ).json() ).find( ( t ) => 'page' === t.type );
const ws = new WebSocket( page.webSocketDebuggerUrl );
await new Promise( ( r ) => ws.addEventListener( 'open', r ) );
let id = 0;
const pending = new Map();
ws.addEventListener( 'message', ( m ) => {
	const msg = JSON.parse( m.data );
	if ( msg.id && pending.has( msg.id ) ) {
		pending.get( msg.id )( msg );
		pending.delete( msg.id );
	}
} );
const send = ( method, params = {} ) => new Promise( ( r ) => {
	const i = ++id;
	pending.set( i, r );
	ws.send( JSON.stringify( { id: i, method, params } ) );
} );
await send( 'Emulation.setDeviceMetricsOverride', { width: 1280, height: 800, deviceScaleFactor: 1, mobile: false } );

function lum( [ r, g, b ] ) {
	const c = ( v ) => ( ( v /= 255 ) <= 0.03928 ? v / 12.92 : ( ( v + 0.055 ) / 1.055 ) ** 2.4 );
	return 0.2126 * c( r ) + 0.7152 * c( g ) + 0.0722 * c( b );
}
const ratio = ( a, b ) => ( Math.max( lum( a ), lum( b ) ) + 0.05 ) / ( Math.min( lum( a ), lum( b ) ) + 0.05 );
const rgb = ( s ) => ( s.match( /[\d.]+/g ) || [] ).map( Number );

let fails = 0;
for ( const c of cases ) {
	const file = join( dir, 'case.html' );
	writeFileSync( file, `<!doctype html><html><head><style>body{margin:0;background:#fff;color:#222;font:16px sans-serif}${ css }</style></head><body><main><p>Page</p></main>${ c.body }${ CREDIT }<script>${ js }</script></body></html>` );
	await send( 'Page.navigate', { url: 'file://' + file } );
	await sleep( 400 );
	const r = await send( 'Runtime.evaluate', { expression: `(() => { const el = document.querySelector('[data-favr-credit]'); const t = el.querySelector('.favr-credit__text'); const s = getComputedStyle(el); return JSON.stringify({ bg: s.backgroundColor, color: getComputedStyle(t).color, opacity: getComputedStyle(t).opacity }); })()`, returnByValue: true } );
	const got = JSON.parse( r.result.result.value );
	const bg = rgb( got.bg );
	const transparent = 4 === bg.length && 0 === bg[ 3 ];
	const shown = c.bg ? c.bg : [ 255, 255, 255 ];
	const problems = [];
	if ( c.bg && ( transparent || bg.slice( 0, 3 ).join() !== c.bg.join() ) ) {
		problems.push( `background ${ got.bg }, wanted rgb(${ c.bg.join( ', ' ) })` );
	}
	if ( ! c.bg && ! transparent ) {
		problems.push( `background ${ got.bg }, wanted none` );
	}
	const contrast = ratio( rgb( got.color ).slice( 0, 3 ), shown ) * 1; // Opacity is part of the colour test below.
	if ( '1' !== got.opacity ) {
		problems.push( `text opacity ${ got.opacity }: the contrast must be in the colour itself` );
	}
	if ( contrast < 4.5 ) {
		problems.push( `contrast ${ contrast.toFixed( 2 ) }:1 is under 4.5:1 (${ got.color })` );
	}
	console.log( ( problems.length ? 'FAIL ' : 'ok   ' ) + c.name + ( problems.length ? ': ' + problems.join( '; ' ) : ` (${ contrast.toFixed( 2 ) }:1)` ) );
	fails += problems.length ? 1 : 0;
}
ws.close();
chrome.kill();
await sleep( 200 );
try {
	rmSync( dir, { recursive: true, force: true } );
} catch ( e ) {}
console.log( fails ? `FAILED: ${ fails }` : 'ALL OK' );
process.exit( fails ? 1 : 0 );
