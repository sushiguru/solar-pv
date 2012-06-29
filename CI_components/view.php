	<script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
    	google.load('visualization', '1', {packages: ['annotatedtimeline']});
    </script>
 
	<script type="text/javascript">
		function drawVisualization() {
        	var data = new google.visualization.DataTable();
			data.addColumn('date','Datetime');
			data.addColumn('number','Power (Wh)');
			data.addColumn('number','Sun\'s Elevation');
			<?php
				foreach($data as $bits)
				{
					foreach($bits as $x=>$y)
					{
						print"data.addRow([new Date($x)," . $y[0] . "," . $y[1] . "]);\n";
					}
				}
			?>
      
       new google.visualization.AnnotatedTimeLine(document.getElementById('chart_div')).
			draw(data,{
                displayAnnotations: true,
                dateFormat:'dd/MM/yyyy HH:mm',
                max:4000,
                min:0,
                scaleColumns:[0,1],
                scaleType:'allfixed',
                displayZoomButtons:false,
                displayRangeSelector:false,
                numberFormats:'0',
                thickness:2,
                fill:50
                });
      }
      google.setOnLoadCallback(drawVisualization);
	</script>
	
	<form method="post" action="http://url_to_your_controller/" id="fmDate" name="fmDate">    
		<strong>Select date:</strong> <select name="d" id="d" onchange="fmDate.submit();">
			<?php
				echo $dates;
			?>
		</select>
	</form>
	
	<div id="chart_div" style="height: 500px;"></div>
