<template>
    <div class="flex-container">
        <div class="flex-geometry" v-if="edit">
            <input ref="file" :id="field.name" type="file" :class="errorClasses" :placeholder="field.name"
                @change="updateMultipolygon($event)" accept=".geojson,.gpx,.kml" />
            <p v-if="hasError" class="my-2 text-danger">
                {{ firstError }}
            </p>
        </div>
    </div>
    <div id="container">
        <div :id="mapRef" class="wm-map"></div>
    </div>
</template>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova'
import "leaflet/dist/leaflet.css";
import L from "leaflet";
import "leaflet.fullscreen/Control.FullScreen.js";
import "leaflet.fullscreen/Control.FullScreen.css";
import "leaflet-draw/dist/leaflet.draw-src.js";
import "leaflet-draw/dist/leaflet.draw-src.css";

const DEFAULT_TILES = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const DEFAULT_ATTRIBUTION = '<a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery (c) <a href="https://www.mapbox.com/">Mapbox</a>';
const VERSION = "0.0.3"
const VERSION_IMAGE = `<img class="version-image" src="https://img.shields.io/badge/wm--map--multi--multipolygon-${VERSION}-blue">`
const DEFAULT_CENTER = [0, 0];
const DEFAULT_MINZOOM = 7;
const DEFAULT_MAXZOOM = 17;
const DEFAULT_DEFAULTZOOM = 8;
const MULTIPOLYGON_OPTIONS = {
    fillColor: '#f03',
    fillOpacity: 0.5,
};

export default {
    name: "MapMultiPolygon",
    mixins: [FormField, HandlesValidationErrors],
    props: ['field', 'edit'],
    data() {
        return {
            mapRef: `mapContainer-${Math.floor(Math.random() * 10000 + 10)}`,
            deleteIcon: null,
            map: null,
            multipolygon: null,
            geojson: null
        }
    },
    methods: {
        /**
         * This code initializes the map by setting various configurations and building components that will be necessary for the map to function properly.
         * First, the code sets a timeout of 300 milliseconds to ensure that the code is executed after a certain delay. 
         * Next, the code sets the center, maximum zoom, minimum zoom, default zoom, and attribution of the map to either the values specified in the this.field object or default values if no value is provided. 
         * The function then calls several other functions to build different features on the map, including an edit mode, the map itself, a multipolygon using a GeoJSON object, and a delete geometry feature.
         * All of these features are necessary to ensure that the map is fully functional and easy to navigate.
         */
        initMap() {
            setTimeout(() => {
                this.center = this.field.center ?? DEFAULT_CENTER;
                this.maxZoom = this.field.maxZoom ?? DEFAULT_MAXZOOM;
                this.minZoom = this.field.minZoom ?? DEFAULT_MINZOOM;
                this.defaultZoom = this.field.defaultZoom ?? DEFAULT_DEFAULTZOOM;
                this.attribution = this.field.attribution ?? DEFAULT_ATTRIBUTION;
                this.initLeafletEditMode();
                this.buildMap();
                this.buildMultipolygon(this.geojson);
                this.buildLeafletEditMode();
                this.buildDeleteGeometry();
            }, 300);
        },
        /**
         * The function first assigns the global JavaScript variable L to the document.L property. 
         * This allows the L variable to be accessible throughout the document. 
         * Next, the function assigns the global L variable to the window.L property, enabling the L variable to be accessible globally across multiple windows or frames. 
         * This code assumes that the L variable is a reference to the Leaflet JavaScript library, which is typically used for creating interactive maps. 
         * The purpose of this function is to initialize editing mode for a Leaflet map, in which a user can add, modify, or delete map features.
         */
        initLeafletEditMode() {
            document.L = L;
            window.L = L;
        },
        /**
         * The function buildMap() creates a map on the DOM element specified by this.mapRef using the JavaScript mapping library Leaflet. If this.field.geojson is not null, 
         * it is parsed as a GeoJSON object and then passed as an argument to this.updateGeojson(). 
         * The function then creates a Leaflet map with options for a full-screen control and a center point specified by this.center and a default zoom level this.defaultZoom. 
         * After this, the function adds a tile layer to the map. The tile layer is specified by a URL in this.field.tiles or, if that is null, a default URL (DEFAULT_TILES). 
         * The layer is given an attribution string composed of this.attribution, which is a property of the object the buildMap() method belongs to and the string VERSION_IMAGE. 
         * Additional options provided include the maximum and minimum zoom levels (this.maxZoom and this.minZoom, respectively) and the ID of the tile set ("mapbox/streets-v11"). 
         * Finally, the tile layer is added to the map.
         */
        buildMap() {
            var currentGeojson = this.field.geojson != null ? JSON.parse(this.field.geojson) : null;
            this.updateGeojson(currentGeojson)
            this.map = L.map(this.mapRef, {
                fullscreenControl: true,
                fullscreenControlOptions: {
                    position: "topleft"
                }
            }).setView(this.center, this.defaultZoom);
            L.tileLayer(this.field.tiles ?? DEFAULT_TILES, {
                attribution: `${this.attribution}, ${VERSION_IMAGE}`,
                maxZoom: this.maxZoom,
                minZoom: this.minZoom,
                id: "mapbox/streets-v11"
            }).addTo(this.map);
        },
        /**
         * @param {*} geojson 
         * This code block creates a custom control in Leaflet that deletes a polygon feature from a geoJSON layer. 
         * The buildDeleteGeometry() function first checks if the this.edit property is true. If it is not, the function returns without doing anything. 
         * The function then extends the default Leaflet L.Control class by creating a new L.Control.deleteGeometry class. 
         * The onAdd() method is added to the L.Control.deleteGeometry class. This method creates a div element that houses an img element for the delete button. 
         * An event listener is added to this delete button that triggers the updateMultipolygon() method, sets the draw mode, and hides the delete button when clicked. 
         * If there is a geojson property present and this.edit is true, the setEditMode() method is called. Otherwise, the delete button is hidden and draw mode is set. 
         * Finally, the L.Control.deleteGeometry class is initialized and added to the Leaflet map at the top right position. 
         * If there is a non-null multipolygon property and this.edit is true, the delete button is made visible.
         */
        buildMultipolygon(geojson) {
            // Fixes the issue where leaflet draw is not able to edit a multipolygon https://github.com/Leaflet/Leaflet.draw/issues/268
            if (geojson != null) {
                // If the GeoJSON object is of type 'MultiPolygon', convert it to a 'Polygon'
                if (geojson.type === 'MultiPolygon') {
                    geojson.type = 'Polygon';
                    // Set the Polygon coordinates to be the first set of coordinates from the MultiPolygon
                    geojson.coordinates = geojson.coordinates[0];
                }
                this.multipolygon = L.geoJson(geojson, {
                    style: MULTIPOLYGON_OPTIONS
                }).addTo(this.map);
                this.map.fitBounds(this.multipolygon.getBounds());
            }
            try {
                if (this.edit) {
                    if (geojson != null) {
                        this.setEditMode();
                    } else {
                        this.setDrawMode();
                    }
                }
            } catch (_) { }
        },
        /**
         * This function builds a custom deleteGeometry control for a Leaflet map. It first checks if the 'edit' property is truthy, and if not, returns. 
         * Next, it creates a custom control by extending the Leaflet Control class. The 'onAdd' method is added to this control, 
         * which creates a delete button and appends it to the custom control. 
         * When the delete button is clicked, it updates the multipolygon, sets the draw mode, and hides the delete icon.
         * The function then checks if the 'edit' property is true and the 'geojson' property is not null. If so, it calls the 'setEditMode' method. 
         * Otherwise, it sets the visibility of the delete button to hidden and calls the 'setDrawMode' method.
         * Finally, the function instantiates the custom deleteGeometry control and adds it to the map in the top-right position. 
         * If there is a multipolygon defined and 'edit' is truthy, the visibility of the delete icon is set to 'visible'.
         */
        buildDeleteGeometry() {
            if (!this.edit) {
                return;
            }
            // Extend the Leaflet Control class to create a custom deleteGeometry control
            L.Control.deleteGeometry = L.Control.extend({
                onAdd: () => {
                    this.deleteIcon = L.DomUtil.create('div')
                    L.DomUtil.addClass(this.deleteIcon, 'delete-button');
                    var img = L.DomUtil.create('img');
                    img.src = 'https://cdn-icons-png.flaticon.com/512/2891/2891491.png';
                    this.deleteIcon.appendChild(img);
                    // Add a click event listener to the delete button
                    L.DomEvent.on(this.deleteIcon, "click", (e) => {
                        L.DomEvent.stopPropagation(e);
                        this.updateMultipolygon(null);
                        this.setDrawMode();
                        this.deleteIcon.style.visibility = "hidden";
                    });
                    if (this.edit && this.geojson != null) {
                        this.setEditMode();
                    } else {
                        this.deleteIcon.style.visibility = "hidden";
                        this.setDrawMode();
                    }
                    return this.deleteIcon;
                }
            });
            L.control.deleteGeometry = function (opts) {
                return new L.Control.deleteGeometry(opts);
            }
            L.control.deleteGeometry({ position: 'topright' }).addTo(this.map);
            if (this.multipolygon != null && this.edit) {
                this.deleteIcon.style.visibility = "visible";
            }
        },
        /**
         * @param {*} event 
         * This function updates a multipolygon on a leaflet map. If the multipolygon already exists, the function removes it from the map. 
         * If the user provides an input event (in this case, uploading a file), the code creates a new FileReader instance to read the contents of the uploaded file. 
         * The function then checks the file type (GPX, KML or JSON) and converts it to GeoJSON if necessary using either the t.gpx() or t.kml() functions or JSON.parse(). 
         * The function then calls updateGeojson(), passing it the converted GeoJSON data. If the data can be processed without any errors, the function calls buildMultipolygon(), passing it the GeoJSON data. 
         * If the data is corrupt, the function hides a delete icon, resets the file input and notifies the user of the error. 
         * In the absence of an input event, the function sets the GeoJSON property to null and clears the file input. 
         */
        updateMultipolygon(event) {
            if (this.multipolygon !== null) {
                this.map.removeLayer(this.multipolygon);
                this.multipolygon = null;
            }
            // If an event was provided create a new FileReader instance to read the contents of the uploaded file
            if (event) {
                const reader = new FileReader();
                let fileName = event.target.files[0].name || '';
                reader.onload = (event) => {
                    let res = event.target.result;
                    // Check the file type and convert it to GeoJSON if necessary
                    if (fileName.indexOf('gpx') > -1) {
                        // If the file is a GPX file, parse the XML and convert it to GeoJSON
                        const parser = new DOMParser().parseFromString(res, 'text/xml');
                        res = t.gpx(parser);
                    } else if (fileName.indexOf('kml') > -1) {
                        // If the file is a KML file, parse the XML and convert it to GeoJSON
                        const parser = new DOMParser().parseFromString(res, 'text/xml');
                        res = t.kml(parser);
                    } else {
                        res = JSON.parse(res);
                    }
                    this.updateGeojson(res)
                    try {
                        this.buildMultipolygon(this.geojson)
                    } catch (_) {
                        this.deleteIcon.style.visibility = "hidden";
                        this.$refs.file.value = null;
                        window.alert('The file is corrupted');
                    }
                };
                reader.readAsText(event.target.files[0]);
            } else {
                this.updateGeojson(null)
                this.$refs.file.value = null;
            }
        },
        /**
         * @param {*} geojson 
         * This is a method called updateGeojson that takes a geojson parameter. The purpose of this method is to update the current geojson property value of the object calling the method. 
         * First, the method assigns the new geojson value to the geojson property of the object with this.geojson = geojson. 
         * Then, it emits an event with this.$emit("geojson", geojson);. This event is emitted with the name "geojson" and passes the geojson value as a parameter. 
         * This code assumes that the object calling the method has a $emit method, which is usually the case in Vue.js components.
         */
        updateGeojson(geojson) {
            this.geojson = geojson;
            this.$emit("geojson", geojson);
        },
        /**
         * The buildLeafletEditMode() function checks if the "edit" flag is set to true. If not, the function returns. If yes, it checks if the current multipolygon is null to determine if drawMode or editMode should be set. 
         * If the current multipolygon is null, it sets the draw mode. Else, it sets the edit mode. An event listener is then added to the map for when a new shape is created on the map. 
         * Upon creation, the function creates a new feature group and adds the shape layer to it. It then converts the feature group to GeoJSON and calls the updateGeojson function with the result.
         * Another event listener is added for when a shape is edited on the map. When this event is triggered, the function stops the event from propagating, 
         * converts the feature group to GeoJSON, and calls the updateGeojson function with the result.
         * Then there are two more event listeners. The first is for when delete mode is stopped, and it stops the event from propagating. 
         * The second is for when draw mode is stopped, and it sets the edit mode and stops the event from propagating.
         */
        buildLeafletEditMode() {
            if (!this.edit) {
                return;
            }
            // If the current multipolygon is null, set the draw mode, otherwise, set the edit mode
            if (this.multipolygon == null) {
                this.setDrawMode();
            } else {
                this.setEditMode();
            }
            // Add an event listener for when a new shape is created on the map
            this.map.on('draw:created', (e) => {
                const layer = e.layer;
                // If the current multipolygon is null, create a new feature group and set its editing options
                if (this.multipolygon === null) {
                    this.multipolygon = L.featureGroup().addTo(this.map);
                    this.drawControl.setDrawingOptions({
                        edit: {
                            featureGroup: this.multipolygon,
                            remove: false
                        }
                    });
                }
                this.multipolygon.addLayer(layer);
                const geojson = this.multipolygon.toGeoJSON();
                this.updateGeojson(geojson);
            });
            // Add an event listener for when a shape is edited on the map
            this.map.on('draw:edited', (e) => {
                L.DomEvent.stopPropagation(e);
                var geojson = this.multipolygon.toGeoJSON();
                this.updateGeojson(geojson);
            });
            // Add an event listener for when the delete mode is stopped
            this.map.on('draw:deletestop', (e) => {
                L.DomEvent.stopPropagation(e);
            });
            // Add an event listener for when the draw mode is stopped
            this.map.on('draw:drawstop', (e) => {
                this.setEditMode();
                L.DomEvent.stopPropagation(e);
            })
        },
        /**
         * The above code defines a function called setEditMode(), which is used to enable the edit mode of a drawing tool. 
         * Inside the function, the existing draw control is removed from the map and then a new draw control is created with editing features enabled. 
         * The existing multipolygon feature group is passed to this new draw control, along with the configuration option 'remove: false' which disables the remove button. 
         * The new draw control is then added to the map. Additionally, the function also toggles the visibility of the deleteIcon element to be visible.
         */
        setEditMode() {
            try {
                this.map.removeControl(this.drawControl);
            } catch (_) { }
            try {
                this.deleteIcon.style.visibility = "visible";
            } catch (_) { }
            this.drawControl = new L.Control.Draw({
                draw: false,
                edit: {
                    featureGroup: this.multipolygon,
                    remove: false
                }
            });
            this.map.addControl(this.drawControl);
        },
        /**
         * This code defines a function called "setDrawMode" that removes an existing drawing control from a map, 
         * hides a delete icon, and adds a new drawing control to the map with specific options.
         * The "try-catch" statements attempt to remove the previous drawing control and hide the delete icon, but if they don't exist, they do nothing.
         * The code then creates a new drawing control using Leaflet.js library, with the ability to draw a polygon with specific options and disallows intersection. 
         * It disables drawing for other shapes like polyline, rectangle, circle, marker, and circlemarker, and also disables editing. 
         * Finally, it adds this new drawing control to the map.
         */
        setDrawMode() {
            try {
                this.map.removeControl(this.drawControl);
            } catch (_) { }
            try {
                this.deleteIcon.style.visibility = "hidden";
            } catch (_) { }
            this.drawControl = new L.Control.Draw({
                draw: {
                    polygon: {
                        shapeOptions: MULTIPOLYGON_OPTIONS,
                        allowIntersection: false,
                    },
                    polyline: false,
                    rectangle: false,
                    circle: false,
                    marker: false,
                    circlemarker: false
                },
                edit: false
            });
            this.map.addControl(this.drawControl);
        }
    },
    mounted() {
        this.initMap();
    },
};
</script>
