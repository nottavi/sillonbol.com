(function(tinymce){tinymce.create('tinymce.plugins.ExternalimgPlugin',{init:function(ed,url){ed.addCommand('mceExternalimg',function(){ed.execCommand('mceCustom',false,'externalimg')});ed.addButton('externalimg',{title:'externalimg',cmd:'mceExternalimg'});ed.onNodeChange.add(function(ed,cm,n){cm.setActive('externalimg',n.nodeName==='SPAN')})},getInfo:function(){return{longname:'External imagen',author:'Carlos Revillo',authorurl:'http://www.tantacom.com',infourl:'http://www.tantacom.com',version:tinymce.majorVersion+"."+tinymce.minorVersion}}});tinymce.PluginManager.add('externalimg',tinymce.plugins.ExternalimgPlugin)})(tinymce);
