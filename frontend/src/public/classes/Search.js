import err from '@/common/js/err';
import axios from 'axios';

export default Search;

function Search(view, revision, searchType, inBackoffice) {
	this.view = view;
	this.searchType = searchType;
	this.revision = revision;
	this.inBackoffice = inBackoffice;
};

Search.prototype.StartSearch = function (t) {
	if (this.preSearch(t)) {
		return;
	}
	if(this.view.retCancel !== null)
	{
		this.view.retCancel('cancelled');
		this.view.retCancel = null;
	}
	var CancelToken = axios.CancelToken;
	var retCancel = null;
	var view = this.view;
	var loc = this;
	view.loading = true;
	axios.get(window.host + '/services/frontend/search', {
    params: { q: t, f: this.searchType, w: this.revision, b: (this.inBackoffice ? '1' : '0') },
		cancelToken: new CancelToken(function executor(c) { retCancel = c; })
		})
		.then(function(res) {
			loc.LoadResults(res.data, t);
			view.loading = false;
		}).catch(function(error) {
			view.loading = false;
			err.errDialog('search', 'completar la búsqueda solicitada', error);
		});
	view.retCancel = retCancel;
};

Search.prototype.preSearch = function (text) {
	var coordParser = require('./ParseCoordinate');
	var ret = new coordParser(text);
	if (!ret.success) {
		return false;
	}
	var item = {
		Id: null,
		Caption: ret.display,
		Type: "P",
		ExtraIds: "",
		Symbol: "fas fa-map-marker-alt",
		Lat: ret.result.y,
		Lon: ret.result.x,
		Extra: "Ubicación"
	};
	this.LoadResults([item], text);
	return true;
};

Search.prototype.LoadResults = function(list, t) {
	var view = this.view;
	view.searched = t;
	view.autolist = list.map(function (el) {
		el.Highlighted = el.Caption;
		el.Class = '';
		return el;
	});
	if (view.autolist.length === 0) {
		view.autolist = [{
			Type: 'N',
			Caption: 'No se encontraron resultados.',
			Highlighted: 'No se encontraron resultados.',
		}];
	}
};
