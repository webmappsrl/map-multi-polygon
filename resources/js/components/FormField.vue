
<template>
  <default-field :field="field">
    <template #field>
      <wm-map :field="field" :attribution="attribution" @geojson="updateForm" :edit=true>
      </wm-map>
    </template>
  </default-field>
</template>
<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova';

export default {
  mixins: [FormField, HandlesValidationErrors],
  props: ['field'],
  methods: {
    updateForm(value) {
      this.geojson = value;
    },
    fill(formData) {
      let geometry = null;
      try {
        geometry = this.geojson && this.geojson.features && this.geojson.features[0] ? this.geojson.features[0].geometry : this.geojson
      } catch (error) {
        window.alert('the file is corrupt');
        console.error(error);
      }
      if (geometry != null) {
        if (geometry.type === 'Polygon') {
          geometry.type = 'MultiPolygon'
          geometry.coordinates = [geometry.coordinates]
        }
        formData.append(this.field.attribute, JSON.stringify(geometry))
      } else {
        formData.append(this.field.attribute, null)
      }
    },
  },
  data() {
    return { geojson: null }
  }
};
</script>
