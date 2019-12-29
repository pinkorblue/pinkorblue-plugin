
// First, checks if it isn't implemented yet.
if (!String.prototype.format) {
  String.prototype.format = function() {
    var args = arguments;
    return this.replace(/{(\d+)}/g, function(match, number) {
      return typeof args[number] != 'undefined'
        ? args[number]
        : match
      ;
    });
  };
}

cookie_info.cookies.forEach(function(cookie){
	if (cookie['exp'] != 0){
		var date = new Date(cookie['exp']*1000);
		var dateString = date.toUTCString();
		var cookieStr = "{0}={1}; expires={2}; path={3}".format(cookie['name'], cookie['value'], dateString, cookie['path']);
		document.cookie = cookieStr;
	} else {
		var cookieStr = "{0}={1}; path={2}".format(cookie['name'], cookie['value'], cookie['path']);
		document.cookie = cookieStr;
	}
})