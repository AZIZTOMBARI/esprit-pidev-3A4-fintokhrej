import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// --- Mercure Chat Subscription Example ---
const sortieId = window.SORTIE_ID || 1; // À adapter selon ton contexte (Twig ou JS)
const mercureUrl = 'http://localhost:3000/.well-known/mercure'; // adapte le port si besoin
const topic = `chat/sortie/${sortieId}`;

if (typeof EventSource !== 'undefined') {
	const eventSource = new EventSource(`${mercureUrl}?topic=${encodeURIComponent(topic)}`);
	eventSource.onmessage = function(event) {
		const data = JSON.parse(event.data);
		console.log('Nouveau message Mercure:', data);
		// TODO: Affiche le message dans ton UI Messenger
	};
}
