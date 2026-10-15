import {cv,contact} from './cv-example-data.js';
import {renderCv} from './cv-view.js';
document.getElementById('cv-example').append(renderCv(cv,contact));
