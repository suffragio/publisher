let geolocation = false;
let latitude = 0;
let longitude = 0;
let slider = 10;
let range = slider * slider * slider;
let address = '';
let markers = [];

function findGetParameter(parameterName) {
  let result = null;
  let tmp = [];
  location.search.substr(1).split("&").forEach(function(item) {
    tmp = item.split("=");
    if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  document.getElementById('proposal').addEventListener('click', function() {
    window.open(`proposal.html?latitude=${latitude}&longitude=${longitude}`, '_blank');
  });

  document.getElementById('proposal-year').setAttribute('value', new Date().getFullYear());

  let tab = findGetParameter('tab');
  if (tab) {
    document.getElementById('citizens-tab').classList.remove('is-active');
    document.getElementById('citizens').style.display = 'none';
    document.getElementById(`${tab}-tab`).classList.add('is-active');
    document.getElementById(tab).style.display = 'block';
  }
  let judge = findGetParameter('judge');
  if (judge) {
    if (judge.startsWith('https://'))
      judge = judge.substring(8);
    if (judge.includes('.'))
      document.getElementById('judge').setAttribute('value', judge);
  }
  latitude = parseFloat(findGetParameter('latitude'));
  longitude = parseFloat(findGetParameter('longitude'));
  let zoom;
  if (isNaN(latitude) || isNaN(longitude)) {
    latitude = 0;
    longitude = 0;
    zoom = 2;
    if (navigator.geolocation)
      navigator.geolocation.getCurrentPosition(getGeolocationPosition);
    fetch('https://ipinfo.io/loc')
      .then((response) => {
        if (response.status == 429)
          console.log("quota exceeded");
        response.text();
      })
      .then((answer) => {
        if (!geolocation) {
          coords = answer.split(',');
          getGeolocationPosition({coords: {latitude: coords[0], longitude: coords[1]}});
        }
      });
  } else
    zoom = 12;
  let map = L.map('map').setView([latitude, longitude], zoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);
  map.whenReady(function() {setTimeout(() => {this.invalidateSize();}, 0);});
  const greenIcon = new L.Icon({
    iconUrl: 'https://cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });
  let marker = L.marker([latitude, longitude]).addTo(map).bindPopup(latitude + ',' + longitude).on('click', updateLabel);
  let circle = L.circle([latitude, longitude], { color: 'red', opacity: 0.4, fillColor: '#f03', fillOpacity: 0.2, radius: range}).addTo(map);
  marker.setPopupContent(`<div style="text-align:center" id="address">${address}</div><div><input type="range" min="5" max="100" value="${slider}" class="slider" id="range"></div>` +
    `<div style="text-align:center;color:#999" id="position">(${latitude}, ${longitude} &plusmn; ${Math.round(range / 100) / 10} km</div></center>`).openPopup();
  document.getElementById('range').addEventListener('input', rangeChanged);
  map.on('click', function(event) {
    marker.setLatLng(event.latlng).openPopup();
    circle.setLatLng(event.latlng);
    latitude = event.latlng.lat;
    longitude = event.latlng.lng;
    updateLabel();
    updatePosition();
    let range = document.getElementById('range');
    range.setAttribute('value', slider);
    range.addEventListener('input', rangeChanged);
  });
  map.on('contextmenu', function(event) { return false; });
  updatePosition();
  document.getElementById('search-citizens').addEventListener('click', function(event) {
    let fieldset = document.getElementById('citizens-fieldset');
    fieldset.setAttribute('disabled', '');
    let searchCitizen = event.currentTarget;
    searchCitizen.classList.add('is-loading');
    const familyName = document.getElementById('family-name').value;
    const givenNames = document.getElementById('given-names').value;
    judge = document.getElementById('judge').value;
    let parameters = `latitude=${latitude}&longitude=${longitude}&range=${range}&judge=https://${judge}`;
    if (familyName)
      parameters += `&familyName=${encodeURI(familyName)}`;
    if (givenNames)
      parameters += `&givenNames=${encodeURI(givenNames)}`;
    fetch(`/api/citizens.php?${parameters}`)
      .then((response) => response.json())
      .then((answer) => {
        markers.forEach(function(marker) {map.removeLayer(marker);});
        markers = [];
        answer.forEach(function(citizen) {
          const name = `${citizen.givenNames} ${citizen.familyName}`;
          const fingerprint = CryptoJS.SHA1(citizen.signature).toString();
          const label = `<div style="text-align:center"><a target="_blank" href="/citizen.html?fingerprint=${fingerprint}"><img src="${citizen.picture}" width="60" height="80"><br>${name}</a></div>`;
          markers.push(L.marker([citizen.latitude, citizen.longitude], {icon: greenIcon}).addTo(map).bindPopup(label));
        });
        fieldset.removeAttribute('disabled');
        searchCitizen.classList.remove('is-loading');
      });
  });

  document.getElementById('proposal-referendum').addEventListener('change', function(event) {
    if (!event.currentTarget.checked)
      document.getElementById('proposal-petition').checked = true;
  });

  document.getElementById('proposal-petition').addEventListener('change', function(event) {
    if (!event.currentTarget.checked)
      document.getElementById('proposal-referendum').checked = true;
  });

  document.getElementById('search-proposals').addEventListener('click', function(event) {
    let fieldset = document.getElementById('proposals-fieldset');
    fieldset.setAttribute('disabled', '');
    let searchProposal = event.currentTarget;
    searchProposal.classList.add('is-loading');
    const query = document.getElementById('proposal-query').value;
    let type = (document.getElementById('proposal-referendum').checked ? 2 : 0) + (document.getElementById('proposal-petition').checked ? 1 : 0);
    fetch(`/api/proposals.php?type=${type}&search=${encodeURIComponent(query)}&latitude=${latitude}&longitude=${longitude}&year=2023&limit=10`)
      .then((response) => response.json())
      .then((answer) => {
        console.log(answer);
        fieldset.removeAttribute('disabled');
        searchProposal.classList.remove('is-loading');
        let section = document.getElementById('proposal-results');
        section.style.display = '';
        section.innerHTML = '';
        if (answer.length == 0) {
          let div = document.createElement('div');
          div.innerHTML = 'No result found, try to refine your search.'
          section.appendChild(div);
          setTimeout(function() {
            section.innerHTML = '';
            section.style.display = 'none';
          }, 3000)
          return;
        }
        let table = document.createElement('table');
        section.appendChild(table);
        table.classList.add('table');
        let thead = document.createElement('thead');
        table.appendChild(thead);
        let tr = document.createElement('tr');
        thead.appendChild(tr);
        let th = document.createElement('th');
        tr.appendChild(th);
        th.innerHTML = 'Area';
        th = document.createElement('th');
        tr.appendChild(th);
        th.innerHTML = 'Title';
        let tbody = document.createElement('tbody');
        table.appendChild(tbody);
        answer.forEach(function(proposal) {
          tr = document.createElement('tr');
          tbody.appendChild(tr);
          let td = document.createElement('td');
          console.log(proposal);
          console.log(proposal.area);
          console.log(proposal.area[0]);
          td.innerHTML = proposal.area[0].split('=')[1];
          tr.appendChild(td);
          td = document.createElement('td');
          td.innerHTML = proposal.title;
          tr.appendChild(td);
        });
      });
  });

  function getGeolocationPosition(position) {
    geolocation = true;
    latitude = position.coords.latitude;
    longitude = position.coords.longitude;
    map.setView([position.coords.latitude, position.coords.longitude], 12);
    setTimeout(updatePosition, 500);
  }
  
  function rangeChanged(event) {
    slider = event.currentTarget.value;
    range = slider * slider * slider;
    circle.setRadius(range);
    updateLabel();
  }
  
  function updatePosition() {
    marker.setLatLng([latitude, longitude]);
    circle.setLatLng([latitude, longitude]);
    fetch(`https://nominatim.openstreetmap.org/reverse.php?format=json&lat=${latitude}&lon=${longitude}&zoom=10`)
      .then((response) => response.json())
      .then((answer) => {
        address = answer.display_name;
        updateLabel();
      });
  }
  
  function updateLabel() {
    document.getElementById("address").innerHTML = address;
    document.getElementById("position").innerHTML = '(' + latitude + ', ' + longitude + ') &plusmn; ' + Math.round(range / 100) / 10 + ' km';
  }
}

function openTab(event, name) {
  let contentTab = document.getElementsByClassName('content-tab');
  for (let i = 0; i < contentTab.length; i++)
    contentTab[i].style.display = 'none';
  let tab = document.getElementsByClassName('tab');
  for (let i = 0; i < tab.length; i++)
    tab[i].classList.remove('is-active');
  document.getElementById(name).style.display = 'block';
  event.currentTarget.classList.add('is-active');
}
