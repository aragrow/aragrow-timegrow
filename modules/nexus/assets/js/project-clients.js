// File: assets/js/project-clients.js
// Shared by the Clock In / Out and Manual Time Entry pages: project tiles carry their client, the
// "Select Project" search shows "Project (Client)" so same-named projects can be told apart, and a
// Client dropdown and keyword box above the tiles (left column) narrow the tiles.
window.TimeGrowProjectClients = (function($) {

  function label(project) {
    return project.clientName ? project.name + ' (' + project.clientName + ')' : project.name;
  }

  /** Projects from the tiles, sorted by client then project name. */
  function collect() {
    const projects = [];
    $('.timegrow-project-tile').each(function() {
      const project = {
        id: $(this).data('project-id'),
        name: String($(this).data('project-name')),
        desc: $(this).data('project-desc'),
        clientId: String($(this).data('client-id') || ''),
        clientName: String($(this).data('client-name') || '')
      };
      project.label = label(project);
      projects.push(project);
    });
    projects.sort((a, b) => a.clientName.localeCompare(b.clientName) || a.name.localeCompare(b.name));
    return projects;
  }

  /** The project a search value names: its full label, or a project name only one client has. */
  function find(projects, value) {
    const byLabel = projects.find(p => p.label === value);
    if (byLabel) return byLabel;
    const byName = projects.filter(p => p.name === value);
    return byName.length === 1 ? byName[0] : undefined;
  }

  /** Fill the hidden select and the search list with the given projects. */
  function fill($select, $datalist, projects) {
    $select.find('option:not(:first)').remove();
    $datalist.empty();
    projects.forEach(function(project) {
      $select.append($('<option>', { value: project.id, text: project.label }));
      $datalist.append($('<option>', { value: project.label }));
    });
  }

  /**
   * Whether a project matches every word typed (in any order, any case), looking in the
   * project and client names. Pure.
   */
  function matches(project, keywords) {
    const words = String(keywords || '').toLowerCase().split(/\s+/).filter(Boolean);
    const text = (project.name + ' ' + project.clientName).toLowerCase();
    return words.every(w => text.indexOf(w) !== -1);
  }

  /**
   * Filters for the project tiles in the left column: a Client dropdown (when there are two or
   * more clients) and a keyword box, placed above the tiles. They narrow the tiles only; the
   * "Select Project" search in the form keeps listing every project.
   *
   * @param {string} prefix Prefix for the element ids, such as "manual" or "clock".
   */
  function addTileFilters(projects, prefix) {
    const $tiles = $('#project-tiles-container');
    if (!$tiles.length || $('#' + prefix + '-tile-search').length) return;

    const clients = [];
    projects.forEach(function(project) {
      if (project.clientId && !clients.some(c => c.id === project.clientId)) {
        clients.push({ id: project.clientId, name: project.clientName });
      }
    });

    const $filters = $('<div>', { class: 'timegrow-tile-filters' });
    let $client = $();
    if (clients.length > 1) {
      $client = $('<select>', { id: prefix + '-client-filter', class: 'timegrow-nexus-client-filter' })
        .append($('<option>', { value: '', text: 'All clients' }));
      clients.forEach(c => $client.append($('<option>', { value: c.id, text: c.name })));
      $filters.append($('<label>', { for: prefix + '-client-filter', class: 'timegrow-nexus-client-label', text: 'Client' })).append($client);
    }
    const $keywords = $('<input>', { type: 'search', id: prefix + '-tile-search', class: 'timegrow-tile-search', placeholder: 'Search clients or projects...' });
    const $none = $('<p>', { class: 'timegrow-nexus-no-match', text: 'No projects match.' }).hide();
    $filters.append($keywords);
    $tiles.before($filters).before($none);

    function apply() {
      const client = $client.length ? $client.val() : '';
      const shownIds = projects
        .filter(p => (client === '' || p.clientId === client) && matches(p, $keywords.val()))
        .map(p => String(p.id));
      $('.timegrow-project-tile').each(function() {
        $(this).toggle(shownIds.indexOf(String($(this).data('project-id'))) !== -1);
      });
      $none.toggle(shownIds.length === 0);
    }

    $client.on('change', apply);
    $keywords.on('input search', apply);
  }

  return { label: label, collect: collect, find: find, fill: fill, matches: matches, addTileFilters: addTileFilters };
})(jQuery);
