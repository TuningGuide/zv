/**
 * Created by velten on 02.06.16.
 */
"use strict";

const program = require("commander");
const o_o = require('yield-yield');
const http = require('http');
const https = require('https');
const request  = require("request");
const express = require("express");
const app  = express();
const cheerio = require('cheerio');
const querystring = require('querystring');

const objection = require('objection');
const Model = objection.Model;
const Knex = require('knex');
const configuration = require('./knexfile')[app.settings.env];
console.log('Using environment "'+app.settings.env+'" for knex configuration.');
const knex = Knex(configuration);
Model.knex(knex);

const Object = require('./models/Object');

program
	.version('0.0.1')
	.option('-p, --port [port_number]', 'set web server port (8888)', '8888')
	.parse(process.argv);

// Here we are configuring express to use body-parser as middle-ware.
const bodyParser = require("body-parser");
app.use(bodyParser.urlencoded({ extended: false }));
app.use(bodyParser.json());
// Deliver static files from folder
app.use(express.static(__dirname));

var server = http.createServer(app).listen(program.port, function () {
	var host = server.address().address;
	var port = server.address().port;

	console.log('Server listening at ', host, port);
	console.log('CTRL + C to shutdown.');
});
//https.createServer({ ... }, app).listen(443);

let config = {
	baseUrl: "http://www.zwangsversteigerung.eu",
	zvsachsen: {
		url: 'https://zvsachsen.de/',
		pdfBaseUrl: 'https://upload.immobilienpool.de/immobilien/00/00/'
	}
};

function getDefaultParams(query) {
	let params = {
		address: 'Berliner Straße 25, 04105 Leipzig',
		distance: '6',
		value_min: '46000',
		value_max: '49000',
	};

	for(let q in query) {
		if(q in params) {
			params[q] = query[q];
		}
	}

	params.utf8 = '✓';
	// 1, 2, ..., 128
	var object_types = new Array(8).fill().map((x,i) => Math.pow(2, i));
	for(var t of object_types) {
		params['object_type['+t+']'] = '1';
	}

	return params;
}

function toNumericID(string) {
	return string.replace(/\D/g, '');
}

function zvEuToZvgPortal(body) {
	let $ = cheerio.load(body);

	let form = $('.more-box form');
	let inputs = form.find('input[type!="hidden"]').get();
	let formData = {};
	for(let input of inputs) {
		formData[$(input).attr('name')] = $(input).attr('value');
	}
	return {uri: form.attr('action'), form: formData};
}

function extractInformation(body) {
	if(/Der Versteigerungstermin.+liegt in der Vergangenheit/.test(body)) {
		return null;
	}

	let fields = ['Zwangsversteigerung', 'Adresse', 'Addresse', 'Objektart', 'Verkehrswert', 'Beschreibung', 'Gläubiger', 'Versteigerungstermin'];
	let mapping = ['id', 'address', 'address', 'kind', 'marketValue', 'description', 'debtee', 'auctionDate'];

	let map = new Map();
	for(let i=0; i < fields.length; i++) {
		map.set(fields[i], mapping[i]);
	}

	let regExp = new RegExp('^(?:'+fields.join('|')+')');
	let $ = cheerio.load(body);
	let data = {};

	let elements = $('#content > h1, #content > p').get();
	for(let el of elements) {
		let text = $(el).text().trim();
		let match = regExp.exec(text);
		if(match) {
			if(match[0] == 'Beschreibung') {
				let erase = /Objektbeschreibung (keine Gewähr für die Richtigkeit): |Gutachten Download unter www.zvsachsen.de/g;
				data[map.get(match[0])] = $(el).next().text().replace(erase, '').trim();
			}
			else {
				let len = match[0].length;
				if(match[0] == 'Addresse') {
					match[0] = 'Adresse';
				}
				data[map.get(match[0])] = text.substring(len + (text[len] == ':' ? 2 : 1 )).trim();
			}
		}
	}

	data['qm'] = [];
	if('description' in data) {
		let regExp = /(\d+).?(\d*)\s*(m²|qm)/g
		let match;
		while(match = regExp.exec(data['description'])) {
			let qm = match[1] + '.' + (match[2] ? match[2] : '00');
			data['qm'].push(qm);
		}
	}

	return data;
}

function* extractPDFLinks(body, zvgId) {
	let $ = cheerio.load(body);
	let wrapper = $('#layout a.ga-download').get();

	let links = [];
	for(let el of wrapper) {
		let params = querystring.parse($(el).attr('href').substring(2));
		let id = params['immoId'];
		//Gutachten 476 K 336-14 L_186515.pdf
		let fileName = 'Gutachten '+zvgId.replace('/20', '-').replace(/\b0/g, '')+' L_'+id+'.pdf';
		let uri = config.zvsachsen.pdfBaseUrl+id[0]+id[1]+'/'+id[2]+id[3]+'/'+id[4]+id[5]+'/'+fileName;

		let response = yield request.head(uri, yield);
		if(response[0].statusCode < 300) {
			links.push(uri);
		}
		else {
			links.push(config.zvsachsen.url+'?'+querystring.stringify(params));
		}
	}

	return links;
}

app.get('/params.json', function(req, res) {
	res.setHeader('Content-Type', 'application/json');
	res.end(JSON.stringify({params: getDefaultParams(req.query)}));
});

app.get('/data.json', o_o(function *(req, res) {
	let requestC = request.defaults({jar: true, baseUrl: config.baseUrl});

	let loginData = {
		"user_session[email]": "veltenheyn@web.de",
		"user_session[password]": "leipzig01",
	};

	let headers = {
		'user-agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36',
		'Origin': config.baseUrl,
		'Referer': config.baseUrl
	};

	let response;
	response = yield requestC.post({uri: "/user_sessions", qs: loginData, followRedirect: false, headers: headers}, yield);
	if(response[0].statusCode > 302) {
		console.error('Login failed: '+response[0].statusCode);
	}

	response = yield requestC({uri: "/umkreissuche", qs: getDefaultParams(req.query), headers: headers}, yield);

	let detailLinks = new Set();

	let $ = cheerio.load(response[1]); // var body = response[1];
	for(let a of $('.ps li a').get()) {
		detailLinks.add($(a).attr('href'));
	}

	let listLinks = new Set();
	for(let a of $($('.pagination')[0]).find('a').get()) {
		if(!isNaN($(a).text())) { // !isNaN == isNumeric
			listLinks.add($(a).attr('href'));
		}
	}

	for(let link of listLinks) {
		let response = yield requestC({uri: link, headers: headers}, yield);
		let $ = cheerio.load(response[1]); // var body = response[1];
		for(let a of $('.ps li a').get()) {
			detailLinks.add($(a).attr('href'));
		}
	}

	let details = [];
	for(let link of detailLinks) {
		requestC = requestC.defaults({baseUrl: ''});
		let response = yield requestC({uri: link, headers: headers}, yield);
		let data = extractInformation(response[1]);
		if(data) {
			data['uri'] = link;

			let formData = {
				azFullText: data['id'],
				_submit: 1,
			};

			response = yield request.get({uri: config.zvsachsen.url, qs: formData, headers: headers}, yield);
			data['expertiseLinks'] = yield extractPDFLinks(response[1], data['id']);

			let object = yield Object.query().select('Object.*').where('id', '=', toNumericID(data['id'])).first();
			for (let prop in object) {
				if(object.hasOwnProperty(prop) && object[prop] && prop != 'id') {
					data[prop] = object[prop];
				}
			}

			details.push(data);
		}

	}

	res.setHeader('Content-Type', 'application/json');
	res.end(JSON.stringify({data: details}));
}));

app.post('/data.json', o_o(function *(req, res) {
	let id = toNumericID(req.query['id']);
	let object = yield Object.query().select('Object.id').where('Object.id', '=', id).first();

	let data = {};
	data[req.body['field']] = req.body['value'];
	let result;
	if(object) {
		result = yield Object.query().patch(data).where('Object.id', '=', id);
	}
	else {
		data.id = id;
		result = yield Object.query().insert(data);
	}

	res.setHeader('Content-Type', 'application/json');
	res.end(JSON.stringify(result));
}));


