<template>
  <PanelItem :index="index" :field="field">
    <template #value>
      <div>
        <p v-if="!field.geojson">Geometria non disponibile</p>
        <wm-map-multi-polygon :field="field" :edit="false" v-else></wm-map-multi-polygon>
      </div>
    </template>
  </PanelItem>
</template>

<script>
export default {
  props: ['index', 'field'],
}
</script>
