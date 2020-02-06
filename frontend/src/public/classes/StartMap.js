import ActiveSelectedMetric from '@/public/classes/ActiveSelectedMetric';
import ActiveLabels from '@/public/classes/ActiveLabels';
import MetricsList from '@/public/classes/MetricsList';
import SaveRoute from '@/public/classes/SaveRoute';
import Clipping from '@/public/classes/Clipping';
import Tutorial from '@/public/classes/Tutorial';
import RestoreRoute from '@/public/classes/RestoreRoute';
import axios from 'axios';
import str from '@/common/js/str';

import h from '@/public/js/helper';
import err from '@/common/js/err';

export default StartMap;

function StartMap(workReference, frameReference, setupMap) {
	this.hash = '';
	this.SetupMap = setupMap;
	this.frameReference = frameReference;
	this.workReference = workReference;
};

StartMap.prototype.Start = function () {
	this.hash = window.location.hash;
	window.accessLink = null;
	window.accessWorkId = null;
	this.workReference.Current = null;

	var args = this.ResolveWorkIdFromUrl();
	if (args && args.workId) {
		// Si tiene un work, va a ese paso
		this.RestoreWork(args.workId, args.link);
	} else {
		// Si no vino indicado un work,
		// inicia por ruta o por definición default de servidor.
		if (new RestoreRoute(null).RouteHasLocation(this.hash)) {
			this.StartByUrl();
		} else {
			this.GetAndStartByDefaultFrame();
		}
	}
};

StartMap.prototype.ResolveWorkIdFromUrl = function () {
	var pathArray = window.location.pathname.split('/');
	if (pathArray.length > 0 && pathArray[pathArray.length - 1] === '') {
		pathArray.pop();
	}
	if (pathArray.length > 0 && pathArray[pathArray.length - 1] === 'map') {
		pathArray.pop();
	}
	var link = null;
	if (pathArray.length > 0 && pathArray[pathArray.length - 1].length === 18) {
			link = pathArray.pop();
	}
	if (pathArray.length === 0 || !str.isNumeric(pathArray[pathArray.length - 1])) {
		return null;
	}
	return { workId: parseInt(pathArray[pathArray.length - 1]), link: link };
};

StartMap.prototype.RestoreWork = function (workId, link) {
	var loc = this;
	window.accessWorkId = workId;
	window.accessLink = link;
	axios.get(/*window.host + */ '/services/works/GetWorkAndDefaultFrame', {
		params: { w: workId },
		headers: (window.accessLink ? { 'Access-Link' : window.accessLink } : {})
	}).then(function(res) {
		loc.workReference.Current = res.data.work;
		loc.ReceiveWorkStartup(loc.workReference.Current.Startup, res.data.frame);
	}).catch(function(error) {
		err.errDialog('GetWork', 'obtener la información del servidor', error);
	});
	return true;
},

StartMap.prototype.ReceiveWorkStartup = function (startup, frame) {
	var hasRoute = new RestoreRoute(null).RouteHasLocation(this.hash);
	if (hasRoute) {
		this.StartByUrl();
		return;
	}
	var setMapPosition;
	if (startup.Type === 'R' && startup.ClippingRegionItemId !== null) {
		setMapPosition = function () {
			window.SegMap.Clipping.SetClippingRegion(startup.ClippingRegionItemId, true, !startup.Selected);
		};
	}
	else if (startup.Type === 'L') {
		this.frameReference.frame.Envelope.Min = startup.Center;
		this.frameReference.frame.Envelope.Max = startup.Center;
		this.frameReference.frame.Zoom = startup.Zoom;

		setMapPosition = function () {
			window.SegMap.SetCenter(startup.Center);
			window.SegMap.SetZoom(startup.Zoom);
		};
	} else {
		// Type === 'D' || 'R' sin región
		this.StartByDefaultFrame(frame);
		return;
	}
	this.SetupMap(setMapPosition);
	this.Finish();
},

StartMap.prototype.StartByUrl = function () {
	var route = this.hash;
	var loc = this;
	var afterLoaded = function() {
		window.SegMap.RestoreRoute.LoadRoute(route);
		loc.Finish();
	};
	this.SetupMap(afterLoaded);
};

StartMap.prototype.GetAndStartByDefaultFrame = function () {
	const loc = this;
	axios.get(/*window.host + */ '/services/clipping/GetDefaultFrame', {
		params: {}
	}).then(function(res) {
		loc.StartByDefaultFrame(res.data);
	}).catch(function(error) {
		err.errDialog('GetDefaultFrame', 'conectarse con el servidor', error);
	});
};

StartMap.prototype.StartByDefaultFrame = function (frame) {
	var loc = this;
	var route = null;
	if (this.hash.length > 2 && this.hash.substr(0, 2) === '#/') {
		route = this.hash;
	}
	this.frameReference.frame = frame;
	var afterLoaded = function() {
			if (route) {
				window.SegMap.RestoreRoute.LoadRoute(route, true);
			}
			window.SegMap.SaveRoute.UpdateRoute();

			if (window.accessWorkId === null) {
				window.SegMap.Tutorial.CheckOpenTutorial();
			}
	};
	loc.SetupMap(afterLoaded);

	if (this.frameReference.frame.Center.Lat && this.frameReference.frame.Center.Lon) {
		window.SegMap.SetCenter(this.frameReference.frame.Center);
	}
	if (this.frameReference.frame.Zoom || this.frameReference.frame.Zoom === 0) {
		window.SegMap.SetZoom(this.frameReference.frame.Zoom);
	}
	this.Finish();
};
StartMap.prototype.Finish = function () {
	window.SegMap.MapsApi.BindEvents();
};

StartMap.prototype.StartByDefaultFrameAndClipping = function (route) {
	const loc = this;
	axios.get(/*window.host + */ '/services/clipping/GetDefaultFrameAndClipping', {
		params: {}
	}).then(function(res) {
		var canvas = res.data.clipping.Canvas;
		res.data.clipping.Canvas = null;

		loc.clipping.Region = res.data.clipping;
		this.frameReference.frame = res.data.frame;
		if (!window.SegMap) {
			var afterLoaded = function() {
				window.SegMap.SaveRoute.UpdateRoute();
				if (route) {
					window.SegMap.RestoreRoute.LoadRoute(route, true);
				}
			};
			loc.SetupMap(afterLoaded);
		}
		if (loc.workToLoad === false) {
			window.SegMap.Tutorial.CheckOpenTutorial();
		}
		if (canvas) {
			window.SegMap.Clipping.FitCurrentRegion();
			window.SegMap.Clipping.SetClippingCanvas(canvas);
		} else {
			if (this.frameReference.frame.Center.Lat && this.frameReference.frame.Center.Lon) {
				window.SegMap.SetCenter(this.frameReference.frame.Center);
			}
			if (this.frameReference.frame.Zoom || this.frameReference.frame.Zoom === 0) {
				window.SegMap.SetZoom(this.frameReference.frame.Zoom);
			}
		}
		loc.Finish();
	}).catch(function(error) {
		err.errDialog('GetDefaultFrameAndClipping', 'conectarse con el servidor', error);
	});
};
