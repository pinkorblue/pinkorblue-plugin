xmlhttp=new XMLHttpRequest();
xmlhttp.open("GET", '/?rest_route=/robera/v1/check' , true);
xmlhttp.setRequestHeader("Content-type", "application/json");
xmlhttp.send(JSON.stringify({}));
