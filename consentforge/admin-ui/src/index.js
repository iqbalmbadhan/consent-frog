import { render } from '@wordpress/element';
import App from './App';

const container = document.getElementById('cf-admin-root');
if (container) {
    render(<App />, container);
}
